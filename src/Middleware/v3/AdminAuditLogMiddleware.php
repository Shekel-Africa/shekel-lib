<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\Request;
use ReflectionMethod;
use Shekel\ShekelLib\Attributes\LogAdminActivity;
use Shekel\ShekelLib\Contracts\AdminActivityLoggerContract;
use Shekel\ShekelLib\Utils\TenantClient;

class AdminAuditLogMiddleware
{
    public function __construct(private AdminActivityLoggerContract $logger) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->maybeLog($request);
        }

        return $response;
    }

    private function maybeLog(Request $request): void
    {
        $uses = $request->route()?->getAction('uses');
        if (!is_string($uses) || !str_contains($uses, '@')) {
            return;
        }

        [$controller, $method] = explode('@', $uses);
        $attributes = (new ReflectionMethod($controller, $method))->getAttributes(LogAdminActivity::class);

        if (empty($attributes)) {
            return;
        }

        /** @var LogAdminActivity $attribute */
        $attribute = $attributes[0]->newInstance();
        $user = auth()->user();

        $subjectId = $request->route($attribute->subjectParam)
            ?? $request->input($attribute->subjectParam);

        try {
            $this->logger->log([
                'actor_id'        => $user->id,
                'actor_department' => $user->department,
                'actor_role_type'  => $user->role_type,
                'subject_id'       => $subjectId,
                'subject_type'     => $attribute->subjectType,
                'action'           => $attribute->action,
                'description'      => $attribute->description,
                'client_id'        => TenantClient::getClientId(),
            ]);
        } catch (\Throwable) {
        }
    }
}
