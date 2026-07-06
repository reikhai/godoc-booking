<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Slot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = [
            ['name' => 'Dr. Amanda Lim', 'specialty' => 'General Practitioner'],
            ['name' => 'Dr. Rajesh Kumar', 'specialty' => 'Cardiologist'],
            ['name' => 'Dr. Wei Chen', 'specialty' => 'Dermatologist'],
        ];

        foreach ($doctors as $attrs) {
            $doctor = Doctor::create($attrs);
            $this->seedSlots($doctor);
        }
    }

    /** Create 30-minute slots, 09:00–12:00, for the next 5 weekdays. */
    private function seedSlots(Doctor $doctor): void
    {
        $day = Carbon::tomorrow('Asia/Singapore')->setTime(9, 0);
        $created = 0;

        while ($created < 5) {
            if ($day->isWeekday()) {
                for ($start = $day->copy(); $start->hour < 12; $start->addMinutes(30)) {
                    Slot::create([
                        'doctor_id' => $doctor->id,
                        'start_at' => $start->copy(),
                        'end_at' => $start->copy()->addMinutes(30),
                    ]);
                }
                $created++;
            }
            $day->addDay()->setTime(9, 0);
        }
    }
}
