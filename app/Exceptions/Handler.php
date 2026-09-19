<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // A bookkeeping rule was broken (unbalanced entry, closed period, voiding twice …).
        // The message is written for the person recording the transaction.
        if ($exception instanceof \App\Services\Accounting\LedgerException) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // Several controllers (reports, warehouses, invoicing) still reference model
        // classes from a multi-warehouse/invoicing subsystem that was never actually
        // built out (see audit). Rather than crashing with a raw 500 stack trace,
        // surface those as a clean "not implemented yet" response.
        if ($exception instanceof \Error && preg_match('/^Class "(.+)" not found$/', $exception->getMessage())) {
            return response()->json([
                'message' => 'This feature is not available yet.',
            ], 501);
        }

        return parent::render($request, $exception);
    }
}
