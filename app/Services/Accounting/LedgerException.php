<?php

namespace App\Services\Accounting;

use DomainException;

/**
 * A bookkeeping rule was broken (unbalanced entry, closed period, …). The message is
 * written for the person recording the transaction, so controllers can show it as-is.
 */
class LedgerException extends DomainException
{
}
