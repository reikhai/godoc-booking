<?php

namespace App\Http\Controllers;

use App\Http\Resources\DoctorResource;
use App\Http\Resources\SlotResource;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DoctorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DoctorResource::collection(Doctor::orderBy('name')->get());
    }

    /**
     * List a doctor's slots. By default only future, available slots are
     * returned; pass ?all=1 to include booked/past ones (with availability flags).
     */
    public function slots(Request $request, Doctor $doctor): AnonymousResourceCollection
    {
        $query = $doctor->slots()
            ->with('activeBooking')
            ->orderBy('start_at');

        if (! $request->boolean('all')) {
            $query->available()->where('start_at', '>=', now());
        }

        return SlotResource::collection($query->get());
    }
}
