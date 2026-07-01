<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\MtnMomoService;
use App\Services\OrangeMoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private MtnMomoService    $mtn,
        private OrangeMoneyService $orange
    ) {}

    // Initier un paiement
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'method'         => 'required|in:mtn_momo,orange_money',
            'phone'          => 'required|string',
        ]);

        $appointment = Appointment::findOrFail($request->appointment_id);

        // Vérifier que c'est bien le patient du RDV
        if ($appointment->patient_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Vérifier que ce n'est pas déjà payé
        if ($appointment->is_paid) {
            return response()->json(['message' => 'Ce rendez-vous est déjà payé.'], 400);
        }

        // Créer l'enregistrement de paiement
        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id'     => $request->user()->id,
            'amount'         => $appointment->fee,
            'currency'       => 'XAF',
            'method'         => $request->method,
            'status'         => 'pending',
        ]);

        // Initier selon la méthode choisie
        if ($request->method === 'mtn_momo') {
            $result = $this->mtn->requestToPay(
                phone:      $request->phone,
                amount:     $appointment->fee,
                externalId: 'DOKITA-' . $payment->id,
                note:       'Consultation Dr. ' . $appointment->doctor->name,
            );

            if ($result['success']) {
                $payment->update(['transaction_id' => $result['reference_id']]);
                return response()->json([
                    'message'        => 'Demande de paiement envoyée. Confirmez sur votre téléphone MTN.',
                    'payment_id'     => $payment->id,
                    'reference_id'   => $result['reference_id'],
                    'method'         => 'mtn_momo',
                    'amount'         => $appointment->fee,
                    'status'         => 'pending',
                ]);
            }

        } elseif ($request->method === 'orange_money') {
            $result = $this->orange->initPayment(
                phone:       $request->phone,
                amount:      $appointment->fee,
                orderId:     'DOKITA-' . $payment->id,
                description: 'Consultation Dr. ' . $appointment->doctor->name,
            );

            if ($result['success']) {
                $payment->update(['transaction_id' => $result['pay_token'] ?? $result['order_id']]);
                return response()->json([
                    'message'     => 'Redirection vers Orange Money.',
                    'payment_id'  => $payment->id,
                    'payment_url' => $result['payment_url'],
                    'method'      => 'orange_money',
                    'amount'      => $appointment->fee,
                    'status'      => 'pending',
                ]);
            }
        }

        // Si erreur
        $payment->update(['status' => 'failed']);
        return response()->json([
            'message' => 'Erreur lors de l\'initiation du paiement.',
            'error'   => $result['error'] ?? 'Erreur inconnue.',
        ], 500);
    }

    // Vérifier le statut d'un paiement
    public function checkStatus(int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->method === 'mtn_momo' && $payment->transaction_id) {
            $result = $this->mtn->checkPaymentStatus($payment->transaction_id);

            if ($result['success'] && $result['status'] === 'successful') {
                $payment->update(['status' => 'completed']);
                $payment->appointment->update(['is_paid' => true]);
            } elseif ($result['success'] && $result['status'] === 'failed') {
                $payment->update(['status' => 'failed']);
            }
        }

        return response()->json([
            'payment_id'     => $payment->id,
            'status'         => $payment->status,
            'amount'         => $payment->amount,
            'method'         => $payment->method,
            'transaction_id' => $payment->transaction_id,
        ]);
    }

    // Webhook MTN — reçoit la confirmation de paiement
    public function webhookMtn(Request $request): JsonResponse
    {
        $referenceId = $request->input('referenceId') ?? $request->input('externalId');

        if (!$referenceId) {
            return response()->json(['message' => 'referenceId manquant.'], 400);
        }

        $payment = Payment::where('transaction_id', $referenceId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Paiement non trouvé.'], 404);
        }

        $status = strtolower($request->input('status', ''));

        if ($status === 'successful') {
            $payment->update(['status' => 'completed']);
            $payment->appointment->update(['is_paid' => true]);
        } elseif ($status === 'failed') {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['message' => 'Webhook reçu.']);
    }

    // Webhook Orange — reçoit la confirmation de paiement
    public function webhookOrange(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $status  = $request->input('status');

        $payment = Payment::where('transaction_id', 'like', "%{$orderId}%")->first();

        if ($payment && $status === 'SUCCESS') {
            $payment->update(['status' => 'completed']);
            $payment->appointment->update(['is_paid' => true]);
        }

        return response()->json(['message' => 'Webhook Orange reçu.']);
    }

    // Historique des paiements du patient
    public function history(Request $request): JsonResponse
    {
        $payments = Payment::where('patient_id', $request->user()->id)
            ->with('appointment.doctor')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'amount'         => number_format($p->amount, 0, ',', ' ') . ' XAF',
                'method'         => $p->method,
                'status'         => $p->status,
                'transaction_id' => $p->transaction_id,
                'doctor'         => $p->appointment?->doctor?->name,
                'date'           => $p->created_at->format('d M Y à H:i'),
            ]);

        return response()->json($payments);
    }
}