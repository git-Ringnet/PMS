<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\FbSubParty;
use App\Models\FbParty;
use Carbon\Carbon;

#[Signature('app:update-party-serving-status')]
#[Description('Update party and sub-party status to serving if arrival time has reached.')]
class UpdatePartyServingStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $currentDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');

        // Find sub-parties that are 'confirmed' and should be 'serving'
        $subParties = FbSubParty::where('status', 'confirmed')
            ->where(function ($query) use ($currentDate, $currentTime) {
                // If arrival_date is before today, it definitely passed
                $query->where('arrival_date', '<', $currentDate)
                      // If arrival_date is today, check if arrival_time is null or <= current time
                      ->orWhere(function ($q) use ($currentDate, $currentTime) {
                          $q->where('arrival_date', '=', $currentDate)
                            ->where(function ($subQ) use ($currentTime) {
                                $subQ->whereNull('arrival_time')
                                     ->orWhere('arrival_time', '<=', $currentTime);
                            });
                      });
            })
            ->get();

        $partyIdsToUpdate = [];

        foreach ($subParties as $sub) {
            $sub->update(['status' => 'serving']);
            $partyIdsToUpdate[] = $sub->party_id;
        }

        if (!empty($partyIdsToUpdate)) {
            $partyIdsToUpdate = array_unique($partyIdsToUpdate);
            FbParty::whereIn('id', $partyIdsToUpdate)
                ->where('status', 'confirmed')
                ->update(['status' => 'serving']);
        }

        $this->info("Updated " . count($subParties) . " sub-parties to serving.");
    }
}
