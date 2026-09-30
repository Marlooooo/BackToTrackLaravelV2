<?php

   namespace App\Services;

   use App\Models\OsyProfile;
   use App\Models\TrainingProgram;
   use Illuminate\Support\Collection;

   class CourseRecommender
   {
      // Maps words an OSY might say to words used in course titles/descriptions.
      // Extend this with the actual TESDA courses you offer.
      private const SYNONYMS = [
         'bartender' => ['bar', 'tender', 'bartending', 'mixology', 'beverage'],
         'bartending' => ['bar', 'tender', 'bartender', 'mixology', 'beverage'],
         'cook' => ['cookery', 'culinary', 'kitchen', 'food'],
         'chef' => ['cookery', 'culinary', 'kitchen'],
         'baker' => ['bread', 'pastry', 'baking'],
         'food' => ['cookery', 'culinary', 'catering'],
         'welder' => ['welding', 'shielded', 'fabrication'],
         'electrician' => ['electrical', 'wiring', 'installation'],
         'mechanic' => ['automotive', 'motorcycle', 'servicing'],
         'driver' => ['driving', 'automotive'],
         'computer' => ['programming', 'technology'],
         'programmer' => ['programming', 'software', 'computer'],
         'barber' => ['barbering', 'hairdressing'],
         'hair' => ['hairdressing', 'barbering', 'beauty'],
         'beauty' => ['cosmetology', 'nail', 'hairdressing'],
         'sewing' => ['dressmaking', 'tailoring', 'garments'],
         'tailor' => ['tailoring', 'dressmaking'],
         'farm' => ['agriculture', 'crop', 'livestock'],
         'carpenter' => ['carpentry', 'construction'],
         'plumber' => ['plumbing', 'pipefitting'],
         'caregiver' => ['caregiving', 'health', 'nursing'],
         'massage' => ['wellness', 'therapy'],
      ];

      private const STOPWORDS = [
         'the', 'and', 'for', 'want', 'wants', 'like', 'likes', 'have', 'has',
         'with', 'that', 'this', 'from', 'become', 'work', 'job', 'his', 'her',
         'she', 'they', 'him', 'them', 'was', 'were', 'are', 'can', 'will',
         'would', 'their', 'about', 'been', 'not', 'but', 'you', 'get', 'learn',
      ];

      // Your training_programs table uses 'active' for courses that can be taken.
      private const AVAILABLE_STATUS = 'active';

      private ?array $synonymMap = null;

      public function recommend(OsyProfile $osy, int $limit = 5): Collection
      {
         $terms = $this->buildTerms($osy);

         if (empty($terms)) {
               return collect();
         }

         return TrainingProgram::query()
               ->where('status', self::AVAILABLE_STATUS)
               ->get()
               ->map(function (TrainingProgram $program) use ($terms) {
                  $title = $this->tokens($program->name);
                  $desc = $this->tokens($program->description);

                  $score = 0;
                  $matched = [];

                  foreach ($terms as $term => $weight) {
                     if (in_array($term, $title, true)) {
                           $score += $weight * 2;
                           $matched[] = $term;
                     } elseif (in_array($term, $desc, true)) {
                           $score += $weight;
                           $matched[] = $term;
                     }
                  }

                  return [
                     'program' => $program,
                     'score' => $score,
                     'matched' => array_values(array_unique($matched)),
                  ];
               })
               ->filter(fn ($r) => $r['score'] > 0)
               ->sortByDesc('score')
               ->take($limit)
               ->values();
      }

      /** term => weight. Career and goals count most. */
      private function buildTerms(OsyProfile $osy): array
      {
         $sources = [
               [$osy->preferred_career, 3],
               [$osy->expressed_goals, 2],
               [$osy->personal_observations, 1],
               [$osy->background_circumstances, 1],
         ];

         $terms = [];
         foreach ($sources as [$text, $weight]) {
               foreach ($this->tokens($text) as $token) {
                  $terms[$token] = max($terms[$token] ?? 0, $weight);
               }
         }

         $synonyms = $this->synonymMap();
         foreach ($terms as $token => $weight) {
               foreach ($synonyms[$token] ?? [] as $synonym) {
                  $terms[$synonym] = max($terms[$synonym] ?? 0, $weight);
               }
         }

         return $terms;
      }

      /** SYNONYMS with every word reduced to its stem, so lookups line up. */
      private function synonymMap(): array
      {
         if ($this->synonymMap === null) {
               $this->synonymMap = [];
               foreach (self::SYNONYMS as $key => $list) {
                  $this->synonymMap[$this->stem($key)] = array_map(
                     fn ($w) => $this->stem($w),
                     $list
                  );
               }
         }

         return $this->synonymMap;
      }

      private function tokens(?string $text): array
      {
         if (! $text) {
               return [];
         }

         $words = preg_split('/[^a-z]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

         $tokens = [];
         foreach ($words as $word) {
               if (strlen($word) > 2 && ! in_array($word, self::STOPWORDS, true)) {
                  $tokens[] = $this->stem($word);
               }
         }

         return array_values(array_unique($tokens));
      }

      /** Crude stemming so tender/tending, cook/cookery, weld/welder all match. */
      private function stem(string $word): string
      {
         foreach (['ing', 'ers', 'er', 'ery', 'ed', 'es', 's'] as $suffix) {
               if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 3) {
                  return substr($word, 0, -strlen($suffix));
               }
         }

         return $word;
      }
   }