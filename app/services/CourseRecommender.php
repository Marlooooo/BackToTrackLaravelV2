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

      // A word that only matched through a synonym counts a bit less than a word the OSY actually said.
      private const SYNONYM_FACTOR = 0.8;

      // Catalog: a course needs at least one keyword (1 point) to be suggested at all.
      private const MIN_CATALOG_SCORE = 1.0;

      // Catalog: a course with at least one strong word (3 points) counts as a real match for a career.
      private const STRONG_SCORE = 3.0;

      // Direct search of Maxima courses (only used for careers the catalog doesn't know).
      private const MIN_DB_SCORE = 3.0;

      // false = a Maxima program counts as offering a course only when its NAME has the
      // course words. Set to true to also accept the program description.
      private const MATCH_DESCRIPTION = false;

      private const NOTE_FIELDS = [
         'background_circumstances',
         'personal_observations',
         'expressed_goals',
      ];

      private ?array $synonymMap = null;
      private ?array $catalog = null;

      /**
       * - Preferred career entered: the matching Maxima course, or exactly 3 alternatives.
       * - No preferred career: 3 courses, one from each interview note field.
       * Every result says whether Maxima really offers it ('available' + real 'program').
       */
      public function recommend(OsyProfile $osy): Collection
      {
         $programs = TrainingProgram::query()
               ->where('status', self::AVAILABLE_STATUS)
               ->get();

         return trim((string) $osy->preferred_career) !== ''
               ? $this->fromCareer($osy, $programs)
               : $this->fromNotes($osy, $programs);
      }
          /** Saves this OSY's current recommendations, replacing any saved earlier. */
      public function saveFor(OsyProfile $osy): void
      {
         $results = $this->recommend($osy)->values();

         DB::transaction(function () use ($osy, $results) {
               TrainingRecommendation::where('osy_profile_id', $osy->id)->delete();

               foreach ($results as $i => $r) {
                  TrainingRecommendation::create([
                     'osy_profile_id' => $osy->id,
                     'training_program_id' => $r['program']?->id,
                     'rank' => $i + 1,
                     'match_score' => $r['score'] ?? null,
                     'course_name' => $r['title'],
                     'career_path' => $r['career_path'],
                     'is_available' => $r['available'],
                     'mode' => $r['mode'],
                     'based_on' => $r['basis'],
                     'relation' => $r['relation'],
                     'matched_terms' => $r['matched'],
                     'generated_at' => now(),
                  ]);
               }
         });
      }

      /**
       * Reads the saved recommendations back in the same shape recommend() returns.
       * Details of available courses come from the live training_programs row, so
       * names and descriptions stay current. A program that is no longer active
       * is shown as Not Available.
       */
      public function saved(OsyProfile $osy): Collection
      {
         return $osy->recommendations()
               ->with('trainingProgram')
               ->get()
               ->map(function (TrainingRecommendation $rec) {
                  $program = $rec->trainingProgram;
                  $available = $rec->is_available
                     && $program !== null
                     && $program->status === self::AVAILABLE_STATUS;

                  return [
                     'mode' => $rec->mode,
                     'basis' => $rec->based_on,
                     'relation' => $rec->relation,
                     'available' => $available,
                     'program' => $available ? $program : null,
                     'title' => $available ? $program->name : $rec->course_name,
                     'career_path' => $rec->career_path,
                     'matched' => $rec->matched_terms ?? [],
                     'score' => $rec->match_score,
                  ];
               })
               ->values();
      }

      /* ------------------------------------------------------------------ */
      /* Preferred career                                                   */
      /* ------------------------------------------------------------------ */

      private function fromCareer(OsyProfile $osy, Collection $programs): Collection
      {
         $words = $this->tokenPairs($osy->preferred_career);

         if (empty($words)) {
               return collect();
         }

         $rows = $this->rankCatalog($words);

         // The catalog doesn't know this career: only look for a Maxima course directly
         if (empty($rows)) {
               $found = $this->searchPrograms($programs, $osy->preferred_career);

               if (! $found) {
                  return collect();
               }

               return collect([[
                  'mode' => 'career_available',
                  'basis' => 'preferred_career',
                  'relation' => 'direct',
                  'available' => true,
                  'program' => $found['program'],
                  'title' => $found['program']->name,
                  'career_path' => null,
                  'matched' => $found['matched'],
                  'score' => $found['score'],
               ]]);
         }

         // 1) A real match that Maxima offers: recommend that one course
         foreach ($rows as $row) {
               if ($row['score'] < self::STRONG_SCORE) {
                  break; // rows are sorted, so no stronger match follows
               }

               $program = $this->findAvailableProgram($row['entry'], $programs);

               if ($program) {
                  return collect([$this->makeResult($row, $program, 'career_available', 'preferred_career')]);
               }
         }

         // 2) Maxima has none: exactly three alternatives, best match first
         $catalog = $this->catalog();
         $picked = array_slice($rows, 0, 3);
         $keys = array_map(fn ($r) => $r['entry']['key'], $picked);

         // Fewer than three direct matches: complete the set with related courses
         foreach ($picked as $row) {
               foreach ($row['entry']['related'] as $relatedKey) {
                  if (count($picked) >= 3) {
                     break 2;
                  }
                  if (in_array($relatedKey, $keys, true) || ! isset($catalog[$relatedKey])) {
                     continue;
                  }

                  $picked[] = [
                     'entry' => $catalog[$relatedKey],
                     'score' => 0.0,
                     'matched' => [],
                     'relation' => 'related',
                  ];
                  $keys[] = $relatedKey;
               }
         }

         // Each alternative is still checked against Maxima's real courses
         return collect($picked)
               ->map(fn ($row) => $this->makeResult(
                  $row,
                  $this->findAvailableProgram($row['entry'], $programs),
                  'career_alternatives',
                  'preferred_career'
               ))
               ->values();
      }

      /** Direct keyword search of Maxima courses (uses SYNONYMS). */
      private function searchPrograms(Collection $programs, string $career): ?array
      {
         $terms = $this->buildTerms([[$career, 3]]);

         if (empty($terms)) {
               return null;
         }

         return $programs
               ->map(fn (TrainingProgram $p) => $this->scoreProgram($p, $terms))
               ->filter(fn ($r) => $r['score'] >= self::MIN_DB_SCORE)
               ->sort(fn ($a, $b) => [$b['score'], $b['title_hits']] <=> [$a['score'], $a['title_hits']])
               ->first();
      }

      /* ------------------------------------------------------------------ */
      /* Interview notes                                                    */
      /* ------------------------------------------------------------------ */

      private function fromNotes(OsyProfile $osy, Collection $programs): Collection
      {
         // Best catalog courses for each note field on its own
         $candidates = [];
         foreach (self::NOTE_FIELDS as $field) {
               $words = $this->tokenPairs($osy->{$field});

               if (empty($words)) {
                  continue;
               }

               foreach (array_slice($this->rankCatalog($words), 0, 5) as $row) {
                  $candidates[] = $row + ['field' => $field];
               }
         }

         // Strongest matches first. Each field and each course is used at most once.
         usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

         $byField = [];
         $used = [];
         foreach ($candidates as $row) {
               $key = $row['entry']['key'];

               if (isset($byField[$row['field']]) || in_array($key, $used, true)) {
                  continue;
               }

               $byField[$row['field']] = $row;
               $used[] = $key;
         }

         // Keep the field order: background, observations, goals
         $results = collect();
         foreach (self::NOTE_FIELDS as $field) {
               if (isset($byField[$field])) {
                  $row = $byField[$field];
                  $results->push($this->makeResult(
                     $row,
                     $this->findAvailableProgram($row['entry'], $programs),
                     'notes',
                     $field
                  ));
               }
         }

         // A field with nothing usable: fill its slot from the notes as a whole.
         // If even that finds nothing, we return fewer than three rather than invent.
         $missing = count(self::NOTE_FIELDS) - $results->count();

         if ($missing > 0) {
               $all = $this->tokenPairs(implode(' ', array_filter(
                  array_map(fn ($f) => $osy->{$f}, self::NOTE_FIELDS)
               )));

               foreach (array_slice($this->rankCatalog($all, $used), 0, $missing) as $row) {
                  $results->push($this->makeResult(
                     $row,
                     $this->findAvailableProgram($row['entry'], $programs),
                     'notes',
                     'interview_notes'
                  ));
               }
         }

         return $results->values();
      }

      /* ------------------------------------------------------------------ */
      /* Catalog + availability                                             */
      /* ------------------------------------------------------------------ */

      /** Catalog courses that fit the given words, best first. $words = stem => original word. */
      private function rankCatalog(array $words, array $excludeKeys = []): array
      {
         if (empty($words)) {
               return [];
         }

         $rows = [];

         foreach ($this->catalog() as $key => $entry) {
               if (in_array($key, $excludeKeys, true)) {
                  continue;
               }

               $scored = $this->scoreCatalogEntry($entry, $words);

               if ($scored['score'] >= self::MIN_CATALOG_SCORE) {
                  $rows[] = ['entry' => $entry, 'relation' => 'direct'] + $scored;
               }
         }

         usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

         return $rows;
      }

      private function scoreCatalogEntry(array $entry, array $words): array
      {
         $score = 0.0;
         $matched = [];

         foreach ($words as $stem => $word) {
               if (in_array($stem, $entry['strong_stems'], true)) {
                  $score += 3;
               } elseif (in_array($stem, $entry['keyword_stems'], true)) {
                  $score += 1;
               } elseif ($this->prefixMatch($stem, $entry['strong_stems'], 5)) {
                  $score += 1.5;
               } elseif ($this->prefixMatch($stem, $entry['keyword_stems'], 5)) {
                  $score += 0.5;
               } else {
                  continue;
               }

               $matched[] = $word;
         }

         return ['score' => $score, 'matched' => $matched];
      }

      /** The real Maxima program for a catalog course, or null if Maxima doesn't offer it. */
      private function findAvailableProgram(array $entry, Collection $programs): ?TrainingProgram
      {
         foreach ($programs as $program) {
               $title = $this->tokens($program->name);
               $desc = self::MATCH_DESCRIPTION ? $this->tokens($program->description) : [];

               foreach ($entry['alias_stems'] as $phrase) {
                  if (! array_diff($phrase, $title) || ($desc && ! array_diff($phrase, $desc))) {
                     return $program;
                  }
               }
         }

         return null;
      }

      private function makeResult(array $row, ?TrainingProgram $program, string $mode, string $basis): array
      {
         return [
               'mode' => $mode,
               'basis' => $basis,
               'relation' => $row['relation'] ?? 'direct',
               'available' => $program !== null,
               'program' => $program,
               'title' => $program?->name ?? $row['entry']['name'],
               'career_path' => implode(' / ', $row['entry']['careers']),
               'matched' => array_values(array_unique($row['matched'])),
               'score' => $row['score'] ?? null,
         ];
      }

      /** CourseCatalog with every word reduced to its stem, built once. */
      private function catalog(): array
      {
         if ($this->catalog === null) {
               $this->catalog = [];

               foreach (CourseCatalog::all() as $key => $entry) {
                  $this->catalog[$key] = $entry + [
                     'key' => $key,
                     'strong_stems' => $this->stemList($entry['strong']),
                     'keyword_stems' => $this->stemList($entry['keywords']),
                     'alias_stems' => array_map(fn ($phrase) => $this->stemList($phrase), $entry['aliases']),
                  ];
               }
         }

         return $this->catalog;
      }

      private function stemList(array $words): array
      {
         return array_values(array_unique(array_map(fn ($w) => $this->stem($w), $words)));
      }

      /* ------------------------------------------------------------------ */
      /* Direct search of Maxima courses (SYNONYMS)                         */
      /* ------------------------------------------------------------------ */

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

      /* ------------------------------------------------------------------ */
      /* Text helpers                                                       */
      /* ------------------------------------------------------------------ */

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

      /** One word starts with the other (at least $min letters), e.g. "electric" and "electrical". */
      private function prefixMatch(string $term, array $tokens, int $min = 4): bool
      {
         if (strlen($term) < $min) {
               return false;
         }

         foreach ($tokens as $token) {
               if (
                  strlen($token) >= $min
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