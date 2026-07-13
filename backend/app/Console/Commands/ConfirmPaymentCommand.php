<?php

namespace App\Console\Commands;

use App\Application\Payment\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Payment\ValueObjects\PaymentStatus;
use Illuminate\Console\Command;

class ConfirmPaymentCommand extends Command
{
    protected $signature = 'payments:confirm
        {outTradeNo? : 外部订单号，不传则列出所有待确认的支付}
        {--force : 即使网关查询失败也强制确认（仅测试用）}';

    protected $description = '手动确认一笔支付（测试 notify 链路）';

    public function handle(
        PaymentRepositoryInterface $paymentRepository,
        PaymentGatewayResolverInterface $gatewayResolver,
        ConfirmPaymentHandler $confirmPaymentHandler,
    ): int {
        $outTradeNo = $this->argument('outTradeNo');

        if ($outTradeNo === null) {
            return $this->listPendingPayments($paymentRepository, $gatewayResolver, $confirmPaymentHandler);
        }

        $payment = $paymentRepository->findByOutTradeNo($outTradeNo);

        if ($payment === null) {
            $this->error("未找到支付记录: {$outTradeNo}");

            return self::FAILURE;
        }

        $this->line("支付记录: {$payment->outTradeNo} / 渠道: {$payment->channel->value} / 状态: {$payment->status->value}");

        if ($this->option('force')) {
            $this->warn('强制确认模式，跳过网关查询');

            $confirmPaymentHandler->handle(
                outTradeNo: $payment->outTradeNo,
                tradeNo: "FORCE_{$payment->outTradeNo}",
                rawNotify: ['source' => 'manual_force_confirm'],
            );

            $this->info("✅ 强制确认完成");

            return self::SUCCESS;
        }

        $gateway = $gatewayResolver->resolve($payment->channel->value);
        $this->line("查询网关...");

        $queryResult = $gateway->queryPayment($payment->outTradeNo);

        if ($queryResult->status->value !== PaymentStatus::SUCCESS) {
            $this->warn("网关返回状态: {$queryResult->status->value}，未确认支付。使用 --force 可强制确认。");

            return self::SUCCESS;
        }

        $confirmPaymentHandler->handle(
            outTradeNo: $payment->outTradeNo,
            tradeNo: $queryResult->tradeNo,
            rawNotify: ['source' => 'manual_confirm', 'gateway_response' => 'query_success'],
        );

        $this->info("✅ 支付已确认: {$payment->outTradeNo} / 交易号: {$queryResult->tradeNo}");

        return self::SUCCESS;
    }

    private function listPendingPayments(
        PaymentRepositoryInterface $paymentRepository,
        PaymentGatewayResolverInterface $gatewayResolver,
        ConfirmPaymentHandler $confirmPaymentHandler,
    ): int {
        $payments = $paymentRepository->findPendingPayments(20);

        if (empty($payments)) {
            $this->info('没有待确认的支付记录');

            return self::SUCCESS;
        }

        $this->info('待确认支付列表:');
        $this->newLine();

        $headers = ['序号', '订单号', '渠道', '金额'];
        $rows = [];

        foreach ($payments as $i => $payment) {
            $rows[] = [
                $i + 1,
                $payment->outTradeNo,
                $payment->channel->value,
                number_format($payment->amount / 100, 2),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->line('使用 php artisan payments:confirm {outTradeNo} 确认指定支付');

        return self::SUCCESS;
    }
}
