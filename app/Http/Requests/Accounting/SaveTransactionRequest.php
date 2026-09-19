<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Record (or correct) an income, expense, transfer or journal entry.
 *
 * The friendly forms (income / expense / transfer) send one amount and two accounts and the
 * controller turns them into balanced debit/credit lines; the journal form sends the lines
 * itself. Which KIND of account is allowed where is checked in the controller (it needs the
 * accounts loaded); this class checks the shape.
 */
class SaveTransactionRequest extends FormRequest
{
    public function authorize()
    {
        return true; // route middleware: permission:manage accounting
    }

    public function rules()
    {
        $type = $this->input('type');
        $simple = in_array($type, ['income', 'expense'], true);
        $transfer = $type === 'transfer';
        $lines = in_array($type, ['journal', 'opening'], true);

        return [
            'type' => ['required', Rule::in(['income', 'expense', 'transfer', 'journal', 'opening'])],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:2000-01-01'],
            'description' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:150'],
            'payment_reference' => ['nullable', 'string', 'max:100'],

            // income / expense / transfer: one amount
            'amount' => [Rule::requiredIf($simple || $transfer), 'nullable', 'numeric', 'gt:0', 'max:99999999999.99'],
            // the category (an income or expense account) and where the money went / came from
            'account_id' => [Rule::requiredIf($simple), 'nullable', 'integer', 'exists:accounts,id'],
            'payment_account_id' => [Rule::requiredIf($simple), 'nullable', 'integer', 'exists:accounts,id'],
            'from_account_id' => [Rule::requiredIf($transfer), 'nullable', 'integer', 'exists:accounts,id'],
            'to_account_id' => [Rule::requiredIf($transfer), 'nullable', 'integer', 'exists:accounts,id', 'different:from_account_id'],

            // journal / opening balances: explicit lines
            'lines' => [Rule::requiredIf($lines), 'nullable', 'array', 'min:2', 'max:50'],
            'lines.*.account_id' => ['required_with:lines', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'date.before_or_equal' => 'The date cannot be in the future.',
            'amount.gt' => 'The amount must be more than zero.',
            'to_account_id.different' => 'Choose two different accounts.',
        ];
    }
}
