<?php

namespace App\Http\Controllers\Admin;

use App\Application\Identity\CreateEmployee\CreateEmployeeHandler;
use App\Application\Identity\DisableEmployee\DisableEmployeeHandler;
use App\Application\Identity\DTO\CreateEmployeeCommand;
use App\Application\Identity\DTO\UpdateEmployeeCommand;
use App\Application\Identity\GetEmployee\GetEmployeeHandler;
use App\Application\Identity\ListEmployees\ListEmployeesHandler;
use App\Application\Identity\UpdateEmployee\UpdateEmployeeHandler;
use App\Domain\Identity\ValueObjects\Role;
use App\Domain\Identity\ValueObjects\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Http\Resources\Admin\EmployeeResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request, ListEmployeesHandler $handler): JsonResponse
    {
        $result = $handler->handle(
            keyword: (string) $request->query('keyword', ''),
            page: max(1, (int) $request->query('page', 1)),
            perPage: max(1, min(100, (int) $request->query('per_page', 20))),
        );

        return ApiResponse::success([
            'items' => EmployeeResource::collection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    public function store(
        CreateEmployeeRequest $request,
        CreateEmployeeHandler $handler,
    ): JsonResponse {
        $operatorRole = Role::fromString($request->user()->role);
        $validated = $request->validated();

        $employee = $handler->handle(
            new CreateEmployeeCommand(
                name: $validated['name'],
                phone: $validated['phone'],
                employeeNo: $validated['employee_no'] ?? null,
                department: $validated['department'] ?? null,
                role: Role::fromString($validated['role'] ?? Role::EMPLOYEE),
            ),
            $operatorRole,
        );

        return ApiResponse::success(new EmployeeResource($employee), 'ok', 201);
    }

    public function show(int $employee, GetEmployeeHandler $handler): JsonResponse
    {
        return ApiResponse::success(new EmployeeResource($handler->handle($employee)));
    }

    public function update(
        UpdateEmployeeRequest $request,
        int $employee,
        UpdateEmployeeHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();
        $operatorRole = Role::fromString($request->user()->role);

        $updated = $handler->handle(
            new UpdateEmployeeCommand(
                employeeId: $employee,
                name: $validated['name'],
                employeeNo: $validated['employee_no'] ?? null,
                department: $validated['department'] ?? null,
                role: Role::fromString($validated['role']),
                status: UserStatus::fromString($validated['status']),
                resetPassword: (bool) ($validated['reset_password'] ?? false),
            ),
            $request->user()->id,
            $operatorRole,
        );

        return ApiResponse::success(new EmployeeResource($updated));
    }

    public function destroy(
        Request $request,
        int $employee,
        DisableEmployeeHandler $handler,
    ): JsonResponse {
        $handler->handle($employee, $request->user()->id);

        return ApiResponse::success();
    }
}
