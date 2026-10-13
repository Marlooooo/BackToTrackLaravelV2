<?php

   namespace App\Services;

   use App\Models\OsyProfile;
   use App\Models\TrainingProgram;
   use Illuminate\Support\Collection;
   use App\Models\TrainingRecommendation;
   use Illuminate\Support\Facades\DB;

   class CourseRecommender
   {
      // Maps words an OSY might say to words used in course titles/descriptions.
      // Keys: lowercase letters only. Extend this with the actual courses you offer.
      private const SYNONYMS = [
         // ---- original entries ----
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

         // ---- food & hospitality ----
         'cooking' => ['cookery', 'culinary', 'kitchen', 'food'],
         'restaurant' => ['cookery', 'food', 'beverage'],
         'catering' => ['cookery', 'food', 'catering'],
         'bake' => ['bread', 'pastry', 'baking'],
         'cake' => ['pastry', 'baking', 'bread'],
         'pastry' => ['bread', 'pastry', 'baking'],
         'barista' => ['barista', 'coffee', 'beverage'],
         'coffee' => ['barista', 'coffee', 'beverage'],
         'waiter' => ['food', 'beverage', 'restaurant'],
         'waitress' => ['food', 'beverage', 'restaurant'],
         'hotel' => ['housekeeping', 'hospitality', 'tourism', 'hotel'],
         'housekeeping' => ['housekeeping', 'hospitality'],
         'janitor' => ['housekeeping', 'hospitality'],
         'cleaning' => ['housekeeping', 'hospitality'],
         'tourism' => ['tourism', 'hospitality'],
         'tour' => ['tourism', 'hospitality'],

         // ---- trades & technical ----
         'electric' => ['electrical', 'wiring', 'installation'],
         'electricity' => ['electrical', 'wiring', 'installation'],
         'wiring' => ['electrical', 'wiring', 'installation'],
         'solar' => ['solar', 'electrical', 'installation'],
         'aircon' => ['refrigeration', 'airconditioning'],
         'refrigerator' => ['refrigeration', 'airconditioning'],
         'weld' => ['welding', 'shielded', 'fabrication'],
         'welding' => ['welding', 'shielded', 'fabrication'],
         'motorcycle' => ['motorcycle', 'automotive', 'servicing'],
         'vehicle' => ['automotive', 'servicing'],
         'automobile' => ['automotive', 'servicing'],
         'repair' => ['servicing', 'repair'],
         'fix' => ['servicing', 'repair'],
         'carpentry' => ['carpentry', 'construction'],
         'woodwork' => ['carpentry', 'construction'],
         'mason' => ['masonry', 'construction'],
         'tile' => ['tile', 'construction'],
         'painter' => ['painting', 'construction'],
         'plumbing' => ['plumbing', 'pipefitting'],
         'construction' => ['carpentry', 'masonry', 'construction'],
         'tricycle' => ['driving', 'automotive'],
         'jeepney' => ['driving', 'automotive'],
         'truck' => ['driving', 'automotive', 'heavy', 'equipment'],
         'crane' => ['heavy', 'equipment'],

         // ---- computers, electronics, creative ----
         'laptop' => ['computer', 'hardware', 'servicing'],
         'cellphone' => ['computer', 'hardware', 'servicing', 'electronics'],
         'phone' => ['computer', 'hardware', 'servicing', 'electronics'],
         'gadget' => ['computer', 'hardware', 'servicing', 'electronics'],
         'electronics' => ['electronics', 'servicing', 'consumer'],
         'internet' => ['programming', 'web', 'computer'],
         'website' => ['programming', 'web', 'software'],
         'coding' => ['programming', 'software', 'computer'],
         'software' => ['programming', 'software', 'computer'],
         'app' => ['programming', 'software'],
         'graphic' => ['graphic', 'visual', 'animation'],
         'drawing' => ['graphic', 'visual', 'illustration', 'animation'],
         'artist' => ['graphic', 'visual', 'illustration', 'animation'],
         'animation' => ['animation', 'graphic', 'visual'],
         'video' => ['animation', 'multimedia', 'visual'],
         'photo' => ['multimedia', 'visual', 'graphic'],
         'encoder' => ['computer', 'encoding', 'data'],
         'encoding' => ['computer', 'encoding', 'data'],

         // ---- business & finance ----
         'business' => ['entrepreneurship', 'bookkeeping', 'sales'],
         'entrepreneur' => ['entrepreneurship', 'bookkeeping', 'sales'],
         'store' => ['entrepreneurship', 'sales', 'bookkeeping'],
         'sell' => ['entrepreneurship', 'sales'],
         'selling' => ['entrepreneurship', 'sales'],
         'sales' => ['entrepreneurship', 'sales'],
         'vendor' => ['entrepreneurship', 'sales'],
         'bookkeeping' => ['bookkeeping', 'accounting'],
         'accounting' => ['bookkeeping', 'accounting'],
         'cashier' => ['bookkeeping', 'accounting', 'sales'],
         'math' => ['bookkeeping', 'accounting'],
         'numbers' => ['bookkeeping', 'accounting'],

         // ---- health & care ----
         'elderly' => ['caregiving', 'health', 'nursing'],
         'patient' => ['caregiving', 'health', 'nursing'],
         'nurse' => ['caregiving', 'health', 'nursing'],
         'nursing' => ['caregiving', 'health', 'nursing'],
         'health' => ['health', 'caregiving', 'nursing'],
         'hospital' => ['health', 'caregiving', 'nursing'],
         'nanny' => ['caregiving', 'childcare'],
         'yaya' => ['caregiving', 'childcare'],
         'babysitter' => ['caregiving', 'childcare'],
         'children' => ['caregiving', 'childcare'],
         'kids' => ['caregiving', 'childcare'],

         // ---- agriculture ----
         'fish' => ['fishery', 'aquaculture', 'agriculture'],
         'fisherman' => ['fishery', 'aquaculture', 'agriculture'],
         'poultry' => ['livestock', 'agriculture'],
         'chicken' => ['livestock', 'poultry', 'agriculture'],
         'pig' => ['livestock', 'agriculture'],
         'hog' => ['livestock', 'agriculture'],
         'livestock' => ['livestock', 'agriculture'],
         'garden' => ['agriculture', 'crop', 'horticulture'],
         'vegetable' => ['agriculture', 'crop', 'horticulture'],

         // ---- fashion & beauty ----
         'dressmaker' => ['dressmaking', 'tailoring', 'garments'],
         'clothes' => ['dressmaking', 'tailoring', 'garments'],
         'fashion' => ['dressmaking', 'tailoring', 'garments', 'apparel'],
         'nail' => ['nail', 'manicure', 'pedicure', 'beauty'],
         'manicure' => ['nail', 'manicure', 'pedicure', 'beauty'],
         'pedicure' => ['nail', 'manicure', 'pedicure', 'beauty'],
         'makeup' => ['beauty', 'cosmetology', 'makeup'],
         'salon' => ['hairdressing', 'barbering', 'beauty', 'cosmetology'],
         'haircut' => ['hairdressing', 'barbering'],
         'stylist' => ['hairdressing', 'barbering', 'beauty'],
         'spa' => ['massage', 'wellness', 'therapy'],
         'hilot' => ['massage', 'wellness', 'therapy'],

         // ---- Filipino words ----
         'magluto' => ['cookery', 'culinary', 'kitchen'],
         'luto' => ['cookery', 'culinary', 'kitchen'],
         'panadero' => ['bread', 'pastry', 'baking'],
         'tinapay' => ['bread', 'pastry', 'baking'],
         'gupit' => ['barbering', 'hairdressing'],
         'barbero' => ['barbering', 'hairdressing'],
         'mananahi' => ['dressmaking', 'tailoring', 'garments'],
         'tahi' => ['dressmaking', 'tailoring', 'garments'],
         'modista' => ['dressmaking', 'tailoring', 'garments'],
         'magsasaka' => ['agriculture', 'crop', 'livestock'],
         'pagsasaka' => ['agriculture', 'crop', 'livestock'],
         'bukid' => ['agriculture', 'crop', 'livestock'],
         'manok' => ['livestock', 'poultry', 'agriculture'],
         'baboy' => ['livestock', 'agriculture'],
         'isda' => ['fishery', 'aquaculture', 'agriculture'],
         'mekaniko' => ['automotive', 'motorcycle', 'servicing'],
         'makina' => ['automotive', 'motorcycle', 'servicing'],
         'kuryente' => ['electrical', 'wiring', 'installation'],
         'elektrisyan' => ['electrical', 'wiring', 'installation'],
         'hinang' => ['welding', 'shielded', 'fabrication'],
         'karpintero' => ['carpentry', 'construction'],
         'kahoy' => ['carpentry', 'construction'],
         'tubero' => ['plumbing', 'pipefitting'],
         'kompyuter' => ['programming', 'technology', 'computer'],
         'katulong' => ['caregiving', 'health', 'nursing'],
         'alaga' => ['caregiving', 'health', 'nursing'],
         'nars' => ['caregiving', 'health', 'nursing'],
         'negosyo' => ['entrepreneurship', 'sales', 'bookkeeping'],
         'tindahan' => ['entrepreneurship', 'sales', 'bookkeeping'],
         'benta' => ['entrepreneurship', 'sales', 'bookkeeping'],
         'masahista' => ['massage', 'wellness', 'therapy'],
      ];

      // Ignored in both the interview text and the course text. The last group is
      // words that appear in nearly every course description and would match everything.
      private const STOPWORDS = [
         'the', 'and', 'for', 'want', 'wants', 'like', 'likes', 'have', 'has',
         'with', 'that', 'this', 'from', 'become', 'work', 'job', 'his', 'her',
         'she', 'they', 'him', 'them', 'was', 'were', 'are', 'can', 'will',
         'would', 'their', 'about', 'been', 'not', 'but', 'you', 'get', 'learn',
         'also', 'just', 'very', 'really', 'because', 'family', 'people',
         'person', 'good', 'year', 'years', 'old', 'myself', 'himself', 'herself',
         'help', 'helps', 'helping', 'parents', 'make', 'makes', 'take', 'takes',
         'time', 'day', 'days', 'one',
         'out', 'school', 'youth', 'osy', 'training', 'course', 'program',
         'programs', 'skill', 'skills', 'tesda', 'level', 'free', 'offer',
         'offered', 'student', 'students', 'provide', 'provides', 'basic',
         'including',
      ];

      private const AVAILABLE_STATUS = 'active';

      // A word that only matched through a synonym counts a bit less than a word
      // the OSY actually said.
      private const SYNONYM_FACTOR = 0.8;

      // A course must reach this score to be suggested at all (keeps unrelated courses out).
      private const MIN_CAREER_SCORE = 2.0;
      private const MIN_NOTES_SCORE = 3.0;

      private ?array $synonymMap = null;

      /**
       * - Preferred career filled in: ONE best course for that career.
       * - Otherwise: up to $limit courses from the interview notes, best first.
       * Each result has 'based_on' = 'preferred_career' | 'interview_notes'.
       */
      public function recommend(OsyProfile $osy, int $limit = 3): Collection
      {
         $programs = TrainingProgram::query()
               ->where('status', self::AVAILABLE_STATUS)
               ->get();

         if ($programs->isEmpty()) {
               return collect();
         }

         $noteTerms = $this->buildTerms($this->noteSources($osy));

         // 1) Preferred career: the single best fit
         $careerTerms = $this->buildTerms([[$osy->preferred_career, 3]]);

         if (! empty($careerTerms)) {
               $best = $this->rank($programs, $careerTerms, self::MIN_CAREER_SCORE, $noteTerms)
                  ->take(1)
                  ->map(fn ($r) => $r + ['based_on' => 'preferred_career'])
                  ->values();

               if ($best->isNotEmpty()) {
                  return $best;
               }
               // No course fits the career words: fall through to the notes
         }

         // 2) Interview notes: top $limit
         if (empty($noteTerms)) {
               return collect();
         }

         return $this->rank($programs, $noteTerms, self::MIN_NOTES_SCORE)
               ->take($limit)
               ->map(fn ($r) => $r + ['based_on' => 'interview_notes'])
               ->values();
      }




      public function recommendAndSave(OsyProfile $osy, int $limit = 3): Collection
      {
         $results = $this->recommend($osy, $limit);

         DB::transaction(function () use ($osy, $results) {
            TrainingRecommendation::where('osy_profile_id', $osy->id)->delete();

            $now = now();

            foreach ($results->values() as $i => $r) {
                  TrainingRecommendation::create([
                     'osy_profile_id'      => $osy->id,
                     'training_program_id' => $r['program']->id,
                     'rank'                => $i + 1,
                     'match_score'         => min($r['score'], 999.99),
                     'based_on'            => $r['based_on'],
                     'matched_terms'       => $r['matched'],
                     'generated_at'        => $now,
                  ]);
            }
         });

         return $results;
      }

      /** Interview notes, strongest first: goals > observations > background. */
      private function noteSources(OsyProfile $osy): array
      {
         return [
               [$osy->expressed_goals, 3],
               [$osy->personal_observations, 2],
               [$osy->background_circumstances, 1],
         ];
      }

      /**
       * Scores every program, drops the ones below $minScore, and sorts best first.
       * $tieTerms only breaks ties between equal scores.
       */
      private function rank(Collection $programs, array $terms, float $minScore, array $tieTerms = []): Collection
      {
         return $programs
               ->map(function (TrainingProgram $program) use ($terms, $tieTerms) {
                  $result = $this->scoreProgram($program, $terms);
                  $result['tie'] = $tieTerms ? $this->scoreProgram($program, $tieTerms)['score'] : 0;

                  return $result;
               })
               ->filter(fn ($r) => $r['score'] >= $minScore)
               ->sort(fn ($a, $b) => [$b['score'], $b['title_hits'], $b['tie']] <=> [$a['score'], $a['title_hits'], $a['tie']])
               ->values();
      }

      private function scoreProgram(TrainingProgram $program, array $terms): array
      {
         $title = $this->tokens($program->name);
         $desc = $this->tokens($program->description);

         $score = 0.0;
         $titleHits = 0;
         $matched = [];

         foreach ($terms as $term => $info) {
               $weight = $info['weight'];

               if (in_array($term, $title, true)) {
                  $score += $weight * 2;
                  $titleHits++;
               } elseif (in_array($term, $desc, true)) {
                  $score += $weight;
               } elseif ($this->prefixMatch($term, $title)) {
                  $score += $weight * 1.5;
                  $titleHits++;
               } elseif ($this->prefixMatch($term, $desc)) {
                  $score += $weight * 0.5;
               } else {
                  continue;
               }

               $matched[] = $info['label'];
         }

         return [
               'program' => $program,
               'score' => $score,
               'title_hits' => $titleHits,
               'matched' => array_values(array_unique($matched)),
         ];
      }

      /**
       * term (stem) => ['weight' => float, 'label' => the word the OSY actually wrote].
       * $sources is a list of [text, weight].
       */
      private function buildTerms(array $sources): array
      {
         $terms = [];

         foreach ($sources as [$text, $weight]) {
               foreach ($this->tokenPairs($text) as $stem => $word) {
                  if (! isset($terms[$stem]) || $weight > $terms[$stem]['weight']) {
                     $terms[$stem] = ['weight' => (float) $weight, 'label' => $word];
                  }
               }
         }

         $direct = $terms;
         $synonyms = $this->synonymMap();

         foreach ($direct as $stem => $info) {
               foreach ($synonyms[$stem] ?? [] as $synonym) {
                  $w = $info['weight'] * self::SYNONYM_FACTOR;

                  if (! isset($terms[$synonym]) || $w > $terms[$synonym]['weight']) {
                     $terms[$synonym] = ['weight' => $w, 'label' => $info['label']];
                  }
               }
         }

         return $terms;
      }

      /** SYNONYMS with every word reduced to its stem. Keys that share a stem are merged. */
      private function synonymMap(): array
      {
         if ($this->synonymMap === null) {
               $this->synonymMap = [];

               foreach (self::SYNONYMS as $key => $list) {
                  $stemKey = $this->stem($key);
                  $stemmed = array_map(fn ($w) => $this->stem($w), $list);

                  $this->synonymMap[$stemKey] = array_values(array_unique(
                     array_merge($this->synonymMap[$stemKey] ?? [], $stemmed)
                  ));
               }
         }

         return $this->synonymMap;
      }

      /** stem => first original word seen for that stem. */
      private function tokenPairs(?string $text): array
      {
         if (! $text) {
               return [];
         }

         $words = preg_split('/[^a-z]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

         $pairs = [];
         foreach ($words as $word) {
               if (strlen($word) > 2 && ! in_array($word, self::STOPWORDS, true)) {
                  $pairs[$this->stem($word)] ??= $word;
               }
         }

         return $pairs;
      }

      private function tokens(?string $text): array
      {
         return array_keys($this->tokenPairs($text));
      }

      /** One word starts with the other (4+ letters), e.g. "electric" and "electrical". */
      private function prefixMatch(string $term, array $tokens): bool
      {
         if (strlen($term) < 4) {
               return false;
         }

         foreach ($tokens as $token) {
               if (
                  strlen($token) >= 4
                  && (str_starts_with($token, $term) || str_starts_with($term, $token))
               ) {
                  return true;
               }
         }

         return false;
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