<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Endpoint agent cuma untuk laptop yang sudah di-approve.
class EnsureDeviceApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->status === DeviceStatus::Approved,
            403,
            'Laptop ini belum di-approve ketua kelas atau koordinator.',
        );

        return $next($request);
    }
}
