<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    /** List bookings, optionally filtered by patient email. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Booking::query()
            ->with(['slot.doctor', 'patient'])
            ->latest();

        if ($email = $request->query('email')) {
            $query->whereHas('patient', fn ($q) => $q->where('email', $email));
        }

        return BookingResource::collection($query->get());
    }

    public function show(Booking $booking): BookingResource
    {
        return new BookingResource($booking->load(['slot.doctor', 'patient']));
    }

    /** Create a (pending) booking for a slot. Safe under concurrency. */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->book(
            (int) $request->input('slot_id'),
            $request->input('patient.name'),
            $request->input('patient.email'),
        );

        return (new BookingResource($booking->load(['slot.doctor', 'patient'])))
            ->response()
            ->setStatusCode(201);
    }

    public function confirm(Booking $booking): BookingResource
    {
        $booking->confirm();

        return new BookingResource($booking->load(['slot.doctor', 'patient']));
    }

    public function cancel(Booking $booking): BookingResource
    {
        $booking->cancel();

        return new BookingResource($booking->load(['slot.doctor', 'patient']));
    }

    public function complete(Booking $booking): BookingResource
    {
        $booking->complete();

        return new BookingResource($booking->load(['slot.doctor', 'patient']));
    }
}
