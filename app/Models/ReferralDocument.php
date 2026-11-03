<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;
   use Illuminate\Support\Collection;

   class ReferralDocument extends Model
   {
      // Only kept for old seeders. The real list lives in default_requirements.
      public const DEFAULTS = [
         'Valid ID',
         'Birth certificate',
         'Barangay certificate',
         'Recent ID photos',
         'Medical certificate',
      ];

      protected $fillable = [
         'referral_id',
         'name',
         'status',
         'remarks',
         'received_at',
         'updated_by',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }

      // Creates the missing checklist rows, but only for OSY who are still Accepted
      public static function ensureFor(Collection $referrals): void
      {
         $referrals = $referrals->where('status', 'Accepted by Maxima');

         if ($referrals->isEmpty()) {
            return;
         }

         $defaults = DefaultRequirement::orderBy('id')->pluck('name')->all();

         $programs = TrainingProgram::whereIn(
            'id',
            $referrals->pluck('training_program_id')->filter()->unique()
         )->get()->keyBy('id');

         $existing = static::whereIn('referral_id', $referrals->pluck('id'))
            ->get(['referral_id', 'name'])
            ->groupBy('referral_id');

         $now  = now();
         $rows = [];

         foreach ($referrals as $referral) {
            $program = $programs->get($referral->training_program_id);
            $names   = $program ? $program->requirementNames() : $defaults;

            if (empty($names)) {
                  continue;
            }

            $have = $existing->get($referral->id, collect())->pluck('name')->all();

            foreach ($names as $name) {
                  if (!in_array($name, $have, true)) {
                     $rows[] = [
                        'referral_id' => $referral->id,
                        'name'        => $name,
                        'status'      => 'pending',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                     ];
                  }
            }
         }

         if ($rows) {
            static::insertOrIgnore($rows);
         }
      }

      protected $appends = ['is_program_requirement'];

      private static array $programExtrasCache = [];

      // True when this row came from the program's own requirements,
      // false for defaults and for items Maxima added by hand to one OSY.
      public function getIsProgramRequirementAttribute(): bool
      {
         if (! isset(self::$programExtrasCache[$this->referral_id])) {
            $program = $this->referral?->trainingProgram;

            self::$programExtrasCache[$this->referral_id] = $program
                  ? array_map('mb_strtolower', $program->extraRequirementNames())
                  : [];
         }

         return in_array(
            mb_strtolower($this->name),
            self::$programExtrasCache[$this->referral_id],
            true
         );
      }
   }