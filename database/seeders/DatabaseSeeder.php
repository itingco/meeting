<?php
namespace Database\Seeders;
use App\Models\MeetingRecipient;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['IT','OPS','Finance','IC','RND','CMC','HC','Legal','CEO','GM'] as $department) {
            MeetingRecipient::firstOrCreate(['department' => $department], [
                'email' => null, 'enabled' => false,
                'is_executive' => in_array($department, ['CEO', 'GM'], true),
            ]);
        }
    }
}
