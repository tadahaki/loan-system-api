<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use App\Models\Payment;
use Carbon\Carbon;

class LoanController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000|max:100000',
            'purpose' => 'required|string',
            'term' => 'required|integer',
            'repayment_frequency' => 'required|string',
            'user_id' => 'required|exists:userborrows,id'
        ]);

        $loan = Loan::create([
            'user_id' => $request->user_id,
            'amount' => $request->amount,
            'purpose' => $request->purpose,
            'term' => $request->term,
            'repayment_frequency' => $request->repayment_frequency,
            'status' => 'Pending'
        ]);

        return response()->json($loan, 201);
    }

    public function index(Request $request)
    {
        $query = Loan::with('user');

        // Only return loans that have been configured (have interest rates)
        if ($request->has('configured') && $request->configured) {
            $query->whereNotNull('interest_rate')
                ->whereNotNull('interest_type');
        }

        return $query->get();
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Approved,Rejected,Cancelled'
        ]);

        $loan = Loan::findOrFail($id);
        $loan->status = $request->status;
        $loan->save();

        return response()->json($loan);
    }

    public function getApprovedLoans()
    {
        return Loan::with('user')
            ->where('status', 'Approved')
            ->whereNull('interest_rate')
            ->get();
    }

    public function updateLoanDetails(Request $request, $id)
    {
        $request->validate([
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_type' => 'required|in:Annual,Monthly,Weekly,Daily'
        ]);

        $loan = Loan::findOrFail($id);

        // Calculate loan details based on interest type and rate
        $loanDetails = $this->calculateLoanDetails(
            $loan->amount,
            $request->interest_rate,
            $loan->term,
            $request->interest_type,
            $loan->repayment_frequency
        );

        $loan->interest_rate = $request->interest_rate;
        $loan->interest_type = $request->interest_type;
        $loan->total_interest = $loanDetails['total_interest'];
        $loan->total_payable = $loanDetails['total_payable'];
        $loan->payment_per_period = $loanDetails['payment_per_period'];
        $loan->number_of_payments = $loanDetails['number_of_payments']; // Add this line
        $loan->save();

        return response()->json($loan);
    }


    public function getUserLoanDetails($userId)
    {
        return Loan::where('user_id', $userId)
            ->whereNotNull('interest_rate')
            ->whereNotNull('interest_type')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getUserLoans($userId)
    {
        return Loan::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function calculateLoanDetails($amount, $interestRate, $term, $interestType, $repaymentFrequency)
    {
        $annualRate = $interestRate / 100;

        // Calculate total interest (simple interest)
        switch ($interestType) {
            case 'Monthly':
                $totalInterest = $amount * ($annualRate / 12) * $term;
                break;
            case 'Weekly':
                $totalInterest = $amount * ($annualRate / 52) * ($term * 4.33);
                break;
            case 'Daily':
                $totalInterest = $amount * ($annualRate / 365) * ($term * 30);
                break;
            default: // Annual
                $totalInterest = $amount * $annualRate * ($term / 12);
        }

        $totalPayable = $amount + $totalInterest;

        // Calculate number of payments based on repayment frequency
        switch ($repaymentFrequency) {
            case 'weekly':
                $numberOfPayments = ceil($term * 4.33); // 4.33 weeks per month
                break;
            case 'bi-weekly':
                $numberOfPayments = ceil($term * 2.17); // 2.17 bi-weekly periods per month
                break;
            case 'monthly':
                $numberOfPayments = $term; // exactly the term in months
                break;
            default:
                $numberOfPayments = $term; // default to monthly
        }

        $paymentPerPeriod = $totalPayable / $numberOfPayments;

        return [
            'total_interest' => round($totalInterest, 2),
            'total_payable' => round($totalPayable, 2),
            'payment_per_period' => round($paymentPerPeriod, 2),
            'number_of_payments' => $numberOfPayments
        ];
    }


    // Your new method below //

    public function processPayment(Request $request)
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'user_id' => 'required|exists:userborrows,id',
            'installment_number' => 'required|integer',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'screenshot' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        // Get the loan and installment data
        $loan = Loan::findOrFail($request->loan_id);
        $repaymentSchedule = $this->generateRepaymentSchedule($loan);
        $installment = collect($repaymentSchedule)->firstWhere('installment', $request->installment_number);

        if (!$installment) {
            return response()->json(['success' => false, 'message' => 'Installment not found'], 404);
        }

        // Check for existing payment
        if (Payment::where('loan_id', $loan->id)->where('installment_number', $request->installment_number)->exists()) {
            return response()->json(['success' => false, 'message' => 'This installment has already been paid'], 422);
        }

        // Handle file upload
        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $file = $request->file('screenshot');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('payment_screenshots', $filename, 'public');
            $screenshotPath = $path;
        }

        // Set status based on payment method
        $status = $request->payment_method === 'Cash' ? 'Completed' : 'Pending Verification';

        // Create payment record
        $payment = Payment::create([
            'loan_id' => $request->loan_id,
            'user_id' => $request->user_id,
            'installment_number' => $request->installment_number,
            'amount' => $installment['amountDue'],
            'penalty_amount' => $installment['overduePenalty'],
            'payment_method' => $request->payment_method,
            'screenshot' => $screenshotPath,
            'status' => $status
        ]);

        return response()->json([
            'success' => true,
            'payment' => $payment,
            'message' => $status === 'Completed'
                ? 'Payment completed successfully'
                : 'Payment submitted for verification'
        ]);
    }

    public function updatePaymentStatus(Request $request, $paymentId)
    {
        $request->validate([
            'status' => 'required|in:Completed,Rejected'
        ]);

        $payment = Payment::findOrFail($paymentId);

        // Update payment status and set payment method to GCash if approved
        $payment->status = $request->status;
        if ($request->status === 'Completed') {
            $payment->payment_method = 'GCash';
        }
        $payment->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully',
            'payment' => $payment
        ]);
    }

    private function generateRepaymentSchedule($loan)
    {
        $schedule = [];
        $paymentAmount = $loan->payment_per_period;
        $numberOfPayments = $loan->number_of_payments;
        $today = Carbon::now();

        // Determine payment frequency in days
        $daysBetweenPayments = $this->getDaysBetweenPayments($loan->repayment_frequency);

        // Get all payments for this loan
        $payments = Payment::where('loan_id', $loan->id)->get();

        for ($i = 1; $i <= $numberOfPayments; $i++) {
            $dueDate = Carbon::parse($loan->created_at)
                ->addDays($i * $daysBetweenPayments);

            // Check if this installment has been paid
            $payment = $payments->firstWhere('installment_number', $i);

            if ($payment) {
                $status = $payment->status === 'Completed' ? 'Paid' : ($payment->status === 'Rejected' ? 'Rejected' : 'Pending Verification');
                $overduePenalty = $payment->penalty_amount;
                $totalOverdue = $paymentAmount + $overduePenalty;
            } else {
                // Calculate overdue penalty if applicable
                $isOverdue = $today->greaterThan($dueDate);
                $status = $isOverdue ? 'Overdue' : 'Unpaid';

                // Calculate 3% penalty of the amount due if overdue
                $overduePenalty = $isOverdue ? round($paymentAmount * 0.03, 2) : 0;

                // Total overdue is amount due + penalty
                $totalOverdue = $paymentAmount + $overduePenalty;
            }

            $schedule[] = [
                'installment' => $i,
                'dueDate' => $dueDate->format('Y-m-d'),
                'amountDue' => $paymentAmount,
                'interestRate' => $loan->interest_rate . '%',
                'status' => $status,
                'overduePenalty' => $overduePenalty,
                'totalOverdue' => $totalOverdue,
                'payment_method' => $payment ? $payment->payment_method : null,
                'screenshot_path' => $payment ? $payment->screenshot_path : null,
                'id' => $payment ? $payment->id : null
            ];
        }

        return $schedule;
    }

    private function getDaysBetweenPayments($frequency)
    {
        switch ($frequency) {
            case 'weekly':
                return 7;
            case 'bi-weekly':
                return 14;
            case 'monthly':
                return 30;
            default:
                return 30;
        }
    }

    public function getRepaymentSchedule($loanId)
    {
        $loan = Loan::findOrFail($loanId);
        $schedule = $this->generateRepaymentSchedule($loan);
        return response()->json($schedule);
    }

    // new method

    public function getLoanPayments($loanId)
    {
        $payments = Payment::where('loan_id', $loanId)
            ->orderBy('installment_number')
            ->get();

        return response()->json($payments);
    }


    ////

    public function processGcashPayment(Request $request)
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'user_id' => 'required|exists:userborrows,id',
            'installment_number' => 'required|integer',
            'amount' => 'required|numeric|min:0',
            'screenshot' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        // Get the loan
        $loan = Loan::findOrFail($request->loan_id);

        // Get the repayment schedule
        $repaymentSchedule = $this->generateRepaymentSchedule($loan);
        $installment = collect($repaymentSchedule)->firstWhere('installment', $request->installment_number);

        if (!$installment) {
            return response()->json([
                'success' => false,
                'message' => 'Installment not found'
            ], 404);
        }

        // Check if already paid
        $existingPayment = Payment::where('loan_id', $loan->id)
            ->where('installment_number', $request->installment_number)
            ->first();

        if ($existingPayment) {
            return response()->json([
                'success' => false,
                'message' => 'This installment has already been paid'
            ], 422);
        }

        // Store the screenshot
        $screenshotPath = $request->file('screenshot')->store('payment-screenshots', 'public');

        // Record the payment as pending verification
        $payment = Payment::create([
            'loan_id' => $request->loan_id,
            'user_id' => $request->user_id,
            'installment_number' => $request->installment_number,
            'amount' => $installment['amountDue'],
            'penalty_amount' => $installment['overduePenalty'],
            'payment_method' => 'GCash',
            'screenshot' => $screenshotPath,
            'status' => 'Pending Verification'
        ]);

        return response()->json([
            'success' => true,
            'payment' => $payment,
            'message' => 'GCash payment submitted for verification'
        ]);
    }

    public function verifyPayment(Request $request, $paymentId)
    {
        $request->validate([
            'status' => 'required|in:Paid,Rejected'
        ]);

        $payment = Payment::findOrFail($paymentId);
        $payment->status = $request->status;
        $payment->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);
    }

    public function updatePayment(Request $request, Payment $payment)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,rejected'
        ]);

        $payment->status = $request->status;
        $payment->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);
    }





    public function getUnpaidBalance($loanId)
    {
        $loan = Loan::findOrFail($loanId);
        $schedule = $this->generateRepaymentSchedule($loan);

        $unpaidBalance = 0;
        foreach ($schedule as $payment) {
            if ($payment['status'] === 'Unpaid' || $payment['status'] === 'Overdue') {
                $unpaidBalance += $payment['amountDue'];
            }
        }

        return response()->json([
            'unpaid_balance' => $unpaidBalance
        ]);
    }


    public function getAllLoans()
    {
        // Get all loans with user relationship, ordered by latest first
        $loans = Loan::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($loans);
    }

    public function getUserPayments($userId)
    {
        // Get all loans for the user
        $loans = Loan::where('user_id', $userId)->pluck('id');

        // Get all payments for these loans, ordered by latest first
        $payments = Payment::whereIn('loan_id', $loans)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }
}
