<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param User $user
     * @param int $amount
     * @param string $currencyCode
     * @param int $terms
     * @param string $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE,
        ]);

        foreach (range(1, $terms) as $term) {
            $amountTerm = floor($amount / $terms);
            if ($term === $terms) {
                $amountTerm = $amount - ($amountTerm * ($terms - 1));
            }
            $loan->scheduledRepayments()->create([
                'amount' => $amountTerm,
                'currency_code' => $currencyCode,
                'due_date' => Carbon::parse($processedAt)->addMonths($term)->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
                'outstanding_amount' => $amountTerm,
            ]);
        }
        return $loan;
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param Loan $loan
     * @param int $amount
     * @param string $currencyCode
     * @param string $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        if ($loan->outstanding_amount == 0 && $loan->status == Loan::STATUS_DUE) {
            $loan->update(['outstanding_amount' => $loan->amount]);
            $scheduledRepayments = $loan->scheduledRepayments;
            foreach ($scheduledRepayments as $scheduledRepayment) {
                $scheduledRepayment->update([
                    'outstanding_amount' => $scheduledRepayment->amount
                ]);
            }
        }

        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt,
        ]);

        $scheduledRepayment = ScheduledRepayment::where('loan_id', $loan->id)
            ->where('due_date', $receivedAt)
            ->first();

        if ($scheduledRepayment == NULL) {
            return $receivedRepayment;
        }

        $lastScheduledRepayment = $loan->scheduledRepayments()->orderBy('id', 'desc')->first();
        if ($scheduledRepayment->due_date == $lastScheduledRepayment->due_date) {
            foreach ($loan->scheduledRepayments as $scheduledRepayment) {
                $scheduledRepayment->update([
                    'status' => ScheduledRepayment::STATUS_REPAID,
                    'outstanding_amount' => 0
                ]);
            }

            $firstScheduledRepayment = $loan->scheduledRepayments()->orderBy('id')->first();
            $lastScheduledRepayment->update([
                'status' => ScheduledRepayment::STATUS_REPAID,
                'outstanding_amount' => 0,
                'due_date' => $firstScheduledRepayment->due_date,
            ]);

            $loan->update([
                'outstanding_amount' => 0,
                'status' => Loan::STATUS_REPAID
            ]);

            return $receivedRepayment;
        }

        if ($scheduledRepayment->amount == $receivedRepayment->amount) {
            $scheduledRepayment->update([
                'status' => ScheduledRepayment::STATUS_REPAID,
                'outstanding_amount' => 0
            ]);

            $outstandingAmount = $loan->outstanding_amount - $receivedRepayment->amount;
            $loan->update([
                'outstanding_amount' => $outstandingAmount,
                'status' => $outstandingAmount == 0 ? Loan::STATUS_REPAID : Loan::STATUS_DUE
            ]);

            return $receivedRepayment;
        }

        if ($scheduledRepayment->amount < $receivedRepayment->amount) {
            $scheduledRepayment->update([
                'amount' => $scheduledRepayment->amount + 1,
                'status' => ScheduledRepayment::STATUS_REPAID,
                'outstanding_amount' => 0
            ]);

            $remainingAmount = $receivedRepayment->amount - $scheduledRepayment->amount;

            $nextReceivedAt = Carbon::parse($receivedAt)->addMonth();

            $nextScheduledRepayment = ScheduledRepayment::query()
                ->where('loan_id', $loan->id)
                ->where('due_date', $nextReceivedAt->format('Y-m-d'))
                ->first();

            $nextScheduledRepayment->update([
                'amount' => $scheduledRepayment->amount,
                'status' => ScheduledRepayment::STATUS_PARTIAL,
                'outstanding_amount' => $remainingAmount
            ]);

            $outstandingAmount = $loan->outstanding_amount - $receivedRepayment->amount;
            $loan->update([
                'outstanding_amount' => $outstandingAmount,
                'status' => $outstandingAmount == 0 ? Loan::STATUS_REPAID : Loan::STATUS_DUE
            ]);

            return $receivedRepayment;
        }
        return $receivedRepayment;
    }
}
