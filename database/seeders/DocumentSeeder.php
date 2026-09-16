<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Office;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offices = Office::all();
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@naap.org'],
            ['name' => 'Admin User', 'username' => 'admin', 'role' => 'ADMIN', 'password' => bcrypt('password')]
        );

        if ($user->role !== 'ADMIN' || $user->username !== 'admin') {
            $user->fill(['role' => 'ADMIN', 'username' => 'admin']);
            $user->save();
        }
        $statuses = ['pending', 'in_transit', 'completed'];
        $priorities = ['low', 'medium', 'high'];
        $types = ['application', 'report', 'memo', 'contract', 'certificate'];

        $documents = [
            ['title' => 'Student Enrollment Application', 'description' => 'Application for new student enrollment', 'type' => 'application', 'priority' => 'high'],
            ['title' => 'Budget Approval Report', 'description' => 'Q4 budget analysis and approval request', 'type' => 'report', 'priority' => 'medium'],
            ['title' => 'Faculty Hiring Memo', 'description' => 'Internal memo for faculty position opening', 'type' => 'memo', 'priority' => 'medium'],
            ['title' => 'Library Access Contract', 'description' => 'Contract for digital library access', 'type' => 'contract', 'priority' => 'low'],
            ['title' => 'Graduation Certificate', 'description' => 'Official graduation certificate request', 'type' => 'certificate', 'priority' => 'high'],
            ['title' => 'Research Grant Application', 'description' => 'Application for research funding', 'type' => 'application', 'priority' => 'high'],
            ['title' => 'IT Infrastructure Report', 'description' => 'Annual IT systems assessment', 'type' => 'report', 'priority' => 'medium'],
            ['title' => 'Student Conduct Policy', 'description' => 'Updated student conduct guidelines', 'type' => 'memo', 'priority' => 'low'],
            ['title' => 'Vendor Service Agreement', 'description' => 'Agreement with external service provider', 'type' => 'contract', 'priority' => 'medium'],
            ['title' => 'Academic Transcript', 'description' => 'Official academic transcript request', 'type' => 'certificate', 'priority' => 'high'],
        ];

        $users = \App\Models\User::all();

        foreach ($documents as $docData) {
            $originOffice = $offices->random();
            $destinationOffice = $offices->where('id', '!=', $originOffice->id)->random();

            $createdAt = now()->subDays(rand(0, 30));
            $status = $statuses[array_rand($statuses)];
            
            // Assign a random receiver user
            $receiverUser = $users->where('id', '!=', $user->id)->random();

            $doc = Document::create([
                'title' => $docData['title'],
                'description' => $docData['description'],
                'type' => $docData['type'],
                'priority' => $docData['priority'],
                'origin_office_id' => $originOffice->id,
                'current_office_id' => $status === 'completed' ? $destinationOffice->id : $originOffice->id,
                'destination_office_id' => $destinationOffice->id,
                'receiver_user_id' => $receiverUser->id,
                'uploaded_by' => $user->id,
                'file_path' => 'documents/sample_' . uniqid() . '.pdf',
                'status' => $status,
                'qr_code' => 'NAAP-' . strtoupper(substr(md5($docData['title']), 0, 8)),
                'created_at' => $createdAt,
                'updated_at' => $status === 'completed' ? $createdAt->copy()->addDays(rand(1, 7)) : $createdAt,
            ]);

            // Create corresponding DocumentRouting record
            \App\Models\DocumentRouting::create([
                'document_id' => $doc->id,
                'from_office_id' => $originOffice->id,
                'to_office_id' => $destinationOffice->id,
                'receiver_user_id' => $receiverUser->id,
                'status' => $status === 'completed' ? 'Completed' : 'Pending',
                'notes' => 'Seeded document routing progression step.',
                'scanned_at' => $status === 'completed' ? $doc->created_at->copy()->addMinutes(15) : null,
                'received_at' => $status === 'completed' ? $doc->updated_at : null,
                'signed_by' => $status === 'completed' ? $receiverUser->name : null,
                'signature' => $status === 'completed' ? 'seeded_signature_base64_placeholder' : null,
            ]);

            // Add some activity logs
            ActivityLog::create([
                'user' => $user->name,
                'action' => 'Document created',
                'document_id' => $doc->id,
                'ip' => '127.0.0.1',
                'meta' => ['title' => $doc->title],
                'created_at' => $createdAt,
            ]);

            if ($status === 'in_transit') {
                ActivityLog::create([
                    'user' => $user->name,
                    'action' => 'Document routed',
                    'document_id' => $doc->id,
                    'ip' => '127.0.0.1',
                    'meta' => ['from' => $originOffice->name, 'to' => $destinationOffice->name],
                    'created_at' => $createdAt->addHours(rand(1, 24)),
                ]);
            }

            if ($status === 'completed') {
                ActivityLog::create([
                    'user' => 'system',
                    'action' => 'Document completed',
                    'document_id' => $doc->id,
                    'ip' => '127.0.0.1',
                    'meta' => ['completed_at' => $doc->updated_at->format('Y-m-d H:i:s')],
                    'created_at' => $doc->updated_at,
                ]);
            }
        }
    }
}
