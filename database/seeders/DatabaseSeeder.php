<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilityUpdateRequest;
use App\Models\User;
use App\Support\FacilityInput;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo accounts, one for each role. Password for both: password
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Demo Admin', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        $resident = User::updateOrCreate(
            ['email' => 'resident@example.com'],
            ['name' => 'Demo Resident', 'password' => Hash::make('password'), 'role' => 'resident']
        );

        $neighbor = User::updateOrCreate(
            ['email' => 'neighbor@example.com'],
            ['name' => 'Demo Neighbor', 'password' => Hash::make('password'), 'role' => 'resident']
        );

        // Sample data only. These are made-up facilities, not real places.
        // Coordinates are illustrative points around Cabanatuan City.
        $sample = [
            [
                'name' => 'Sample Barangay Health Center', 'category' => 'Health',
                'address' => 'Sample Street, Sample Barangay', 'latitude' => 15.4850, 'longitude' => 120.9730,
                'features' => ['has_ramp', 'has_accessible_entrance', 'has_accessible_parking', 'has_wide_doorways', 'has_priority_lane'],
                'amenities' => ['priority_seating', 'assistance_staff', 'wheelchair_loan'],
                'hours' => 'Mon to Fri, 8:00 AM to 5:00 PM', 'contact' => '(044) 000-0001',
                'surface' => 'Smooth concrete or tiles', 'slope' => 'Flat',
                'floors' => [['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => 'Consultation room, pharmacy, waiting area']],
                'status' => 'approved', 'owner' => $resident,
            ],
            [
                'name' => 'Sample Community Library', 'category' => 'Education',
                'address' => 'Sample Avenue, Sample Barangay', 'latitude' => 15.4900, 'longitude' => 120.9800,
                'features' => ['has_ramp', 'has_handrails'],
                'amenities' => ['free_entry', 'drinking_water'],
                'hours' => 'Mon to Sat, 9:00 AM to 6:00 PM', 'contact' => '',
                'surface' => 'Paved with some cracks', 'slope' => 'Gentle slope',
                'floors' => [
                    ['level' => 0, 'label' => 'Ground floor', 'access' => 'ramp', 'services' => 'Reading room, front desk'],
                    ['level' => 1, 'label' => 'Second floor', 'access' => 'stairs', 'services' => 'Computer lab, archives'],
                ],
                'status' => 'approved', 'owner' => $resident,
            ],
            [
                'name' => 'Sample Municipal Hall', 'category' => 'Government',
                'address' => 'Sample Plaza, Sample Poblacion', 'latitude' => 15.4869, 'longitude' => 120.9734,
                'features' => ['has_ramp', 'has_accessible_entrance', 'has_accessible_restroom', 'has_accessible_parking', 'has_elevator', 'has_wide_doorways', 'has_handrails', 'has_tactile_paths', 'has_accessible_signage', 'has_priority_lane'],
                'amenities' => ['priority_seating', 'assistance_staff', 'sign_language', 'wheelchair_loan', 'guide_dog'],
                'hours' => 'Mon to Fri, 8:00 AM to 5:00 PM', 'contact' => '(044) 000-0002',
                'surface' => 'Smooth concrete or tiles', 'slope' => 'Flat',
                'floors' => [
                    ['level' => -1, 'label' => 'Basement parking', 'access' => 'elevator', 'services' => 'Accessible parking slots'],
                    ['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => 'Records, cashier, PWD help desk'],
                    ['level' => 1, 'label' => 'Second floor', 'access' => 'elevator', 'services' => 'Mayor\'s office, permits'],
                    ['level' => 2, 'label' => 'Third floor', 'access' => 'elevator', 'services' => 'Session hall'],
                ],
                'status' => 'approved', 'owner' => $resident,
            ],
            [
                'name' => 'Sample Public Market', 'category' => 'Commercial',
                'address' => 'Sample Road, Sample Poblacion', 'latitude' => 15.4826, 'longitude' => 120.9697,
                'features' => ['has_accessible_entrance', 'has_wide_doorways'],
                'amenities' => ['weekend_open'],
                'hours' => 'Daily, 5:00 AM to 7:00 PM', 'contact' => '',
                'surface' => 'Gravel or sand', 'slope' => 'Flat',
                'floors' => [['level' => 0, 'label' => 'Market floor', 'access' => 'ground', 'services' => 'Fresh produce, dry goods, food stalls']],
                'status' => 'approved', 'owner' => $neighbor,
            ],
            [
                'name' => 'Sample Parish Church', 'category' => 'Religious',
                'address' => 'Sample Street, Sample Poblacion', 'latitude' => 15.4893, 'longitude' => 120.9688,
                'features' => ['has_ramp', 'has_accessible_entrance', 'has_wide_doorways', 'has_handrails'],
                'amenities' => ['weekend_open', 'free_entry', 'priority_seating'],
                'hours' => 'Daily, 5:30 AM to 8:00 PM', 'contact' => '',
                'surface' => 'Paved with some cracks', 'slope' => 'Gentle slope',
                'floors' => [['level' => 0, 'label' => 'Main hall', 'access' => 'ramp', 'services' => 'Worship area, reserved wheelchair spaces']],
                'status' => 'approved', 'owner' => $neighbor,
            ],
            [
                'name' => 'Sample Central School', 'category' => 'Education',
                'address' => 'Sample Lane, Sample Barangay', 'latitude' => 15.4795, 'longitude' => 120.9779,
                'features' => ['has_ramp', 'has_accessible_entrance', 'has_handrails', 'has_accessible_restroom', 'has_accessible_signage'],
                'amenities' => ['assistance_staff', 'drinking_water'],
                'hours' => 'Mon to Fri, 7:00 AM to 5:00 PM', 'contact' => '(044) 000-0003',
                'surface' => 'Smooth concrete or tiles', 'slope' => 'Flat',
                'floors' => [
                    ['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => 'Principal\'s office, clinic, Grades 1 to 3'],
                    ['level' => 1, 'label' => 'Second floor', 'access' => 'stairs', 'services' => 'Grades 4 to 6'],
                ],
                'status' => 'approved', 'owner' => $neighbor,
            ],
            [
                'name' => 'Sample Bus Terminal', 'category' => 'Transportation',
                'address' => 'Sample Highway, Sample Barangay', 'latitude' => 15.4932, 'longitude' => 120.9742,
                'features' => ['has_accessible_entrance', 'has_accessible_signage', 'has_priority_lane', 'has_accessible_parking'],
                'amenities' => ['priority_seating', 'assistance_staff'],
                'hours' => 'Daily, 4:00 AM to 10:00 PM', 'contact' => '',
                'surface' => 'Mixed', 'slope' => 'Flat',
                'floors' => [['level' => 0, 'label' => 'Terminal floor', 'access' => 'ground', 'services' => 'Ticketing, waiting area, boarding bays']],
                'status' => 'approved', 'owner' => $resident,
            ],
            [
                'name' => 'Sample Covered Court', 'category' => 'Recreation',
                'address' => 'Sample Road, Sample Barangay', 'latitude' => 15.4780, 'longitude' => 120.9660,
                'features' => ['has_accessible_entrance'],
                'amenities' => ['free_entry'],
                'hours' => '', 'contact' => '', 'surface' => 'Smooth concrete or tiles', 'slope' => 'Flat',
                'floors' => [['level' => 0, 'label' => 'Court level', 'access' => 'ground', 'services' => '']],
                'notes' => 'Waiting for review. Sample record for the demo.',
                'status' => 'pending', 'owner' => $resident,
            ],
            [
                'name' => 'Sample Rural Health Unit Annex', 'category' => 'Health',
                'address' => 'Sample Hill Road, Sample Barangay', 'latitude' => 15.4958, 'longitude' => 120.9811,
                'features' => ['has_accessible_entrance'],
                'amenities' => [],
                'hours' => '', 'contact' => '', 'surface' => 'Gravel or sand', 'slope' => 'Steep slope',
                'floors' => [['level' => 0, 'label' => 'Ground floor', 'access' => 'stairs', 'services' => '']],
                'notes' => 'Sample record for the demo.',
                'status' => 'rejected', 'owner' => $resident,
                'admin_remarks' => 'The approach is described as steep with stairs only, but the entrance is marked accessible. Please check the entrance details and resubmit.',
            ],
        ];

        foreach ($sample as $row) {
            $approved = $row['status'] === 'approved';

            $snapshot = [
                'name' => $row['name'],
                'category' => $row['category'],
                'address' => $row['address'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'hours' => $row['hours'],
                'contact' => $row['contact'],
                'surface' => $row['surface'],
                'slope' => $row['slope'],
                'notes' => $row['notes'] ?? 'Sample record for the demo.',
                'amenities' => $row['amenities'],
                'floors' => $row['floors'],
            ];

            foreach (array_keys(Facility::FEATURES) as $feature) {
                $snapshot[$feature] = in_array($feature, $row['features'], true);
            }

            $facility = Facility::firstOrNew(['name' => $row['name'], 'user_id' => $row['owner']->id]);
            $facility->status = $row['status'];
            $facility->admin_remarks = $row['admin_remarks'] ?? null;
            $facility->reviewed_by = $row['status'] === 'pending' ? null : $admin->id;
            $facility->reviewed_at = $row['status'] === 'pending' ? null : now();
            $facility->user_id = $row['owner']->id;

            FacilityInput::save($facility, $snapshot);
        }

        // One sample update request, so the admin review of updates can be demonstrated.
        $library = Facility::where('name', 'Sample Community Library')->with('floors')->first();
        if ($library && ! $library->updateRequests()->exists()) {
            $proposed = $library->snapshot();
            $proposed['has_elevator'] = true;
            $proposed['has_accessible_entrance'] = true;
            $proposed['floors'][1]['access'] = 'elevator';

            FacilityUpdateRequest::create([
                'facility_id' => $library->id,
                'user_id' => $neighbor->id,
                'changes' => $proposed,
                'reason' => 'The library installed a small elevator and a step-free side entrance last month.',
                'status' => 'pending',
            ]);
        }
    }
}
