<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 调试 APK 下载（ngrok 通道）
Route::get('/app-debug.apk', function () {
    $path = storage_path('app/debug/app-debug.apk');

    if (! file_exists($path)) {
        abort(404);
    }

    $size = filesize($path);

    return response()->stream(function () use ($path) {
        $handle = fopen($path, 'rb');
        if ($handle) {
            while (! feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
    }, 200, [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Length' => $size,
        'Content-Disposition' => 'attachment; filename="app-debug.apk"',
        'Cache-Control' => 'public',
    ]);
});

// APK 下载引导页面（手机浏览器用，避免 ngrok Content-Type 问题）
Route::get('/download-apk', function () {
    $path = storage_path('app/debug/app-debug.apk');

    $exists = file_exists($path);
    $size = $exists ? filesize($path) : 0;
    $sizeMb = $exists ? number_format($size / 1048576, 1) : '0';

    return response()->stream(function () use ($exists, $sizeMb) {
        echo '<!DOCTYPE html>';
        echo '<html lang="zh-CN">';
        echo '<head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>下载 King Shop</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5;padding:20px}';
        echo '.card{background:#fff;border-radius:16px;padding:40px 32px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:320px;width:100%}';
        echo '.icon{font-size:48px;margin-bottom:12px}';
        echo 'h1{font-size:22px;margin:0 0 8px;color:#1a1a1a;font-weight:700}';
        echo 'p{font-size:14px;color:#888;margin:0 0 28px;line-height:1.6}';
        echo '.btn{display:block;background:#1976d2;color:#fff;text-decoration:none;padding:16px;border-radius:12px;font-size:17px;font-weight:600;transition:background .15s}';
        echo '.btn:hover{background:#1565c0}';
        echo '.btn:active{background:#0d47a1}';
        echo '.size{font-size:12px;color:#aaa;margin-top:16px}';
        echo '</style></head><body>';
        echo '<div class="card">';
        echo '<div class="icon">📦</div>';
        echo '<h1>King Shop 调试版</h1>';
        echo '<p>APK 安装包，下载后直接安装即可</p>';

        if ($exists) {
            echo '<a class="btn" href="/app-debug.apk" download>下载 APK（' . $sizeMb . ' MB）</a>';
        } else {
            echo '<div style="color:#e53935;font-size:14px">APK 文件暂未构建</div>';
        }

        echo '<div class="size">Android 安装前请开启「允许安装未知来源应用」</div>';
        echo '</div></body></html>';
    }, 200, ['Content-Type' => 'text/html; charset=utf-8']);
});
