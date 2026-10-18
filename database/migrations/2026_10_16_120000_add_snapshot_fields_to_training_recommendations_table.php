<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      private const TABLE = 'training_recommendations';

      public function up(): void
      {
         // Courses Maxima doesn't offer have no training_programs row. Safe to repeat.
         Schema::table(self::TABLE, function (Blueprint $table) {
               $table->unsignedBigInteger('training_program_id')->nullable()->change();
         });

         // Add each column only if it isn't there yet
         $columns = [
               'course_name'   => fn (Blueprint $t) => $t->string('course_name')->nullable(),
               'career_path'   => fn (Blueprint $t) => $t->string('career_path')->nullable(),
               'is_available'  => fn (Blueprint $t) => $t->boolean('is_available')->default(false),
               'mode'          => fn (Blueprint $t) => $t->string('mode', 30)->nullable(),
               'based_on'      => fn (Blueprint $t) => $t->string('based_on', 40)->nullable(),
               'relation'      => fn (Blueprint $t) => $t->string('relation', 20)->default('direct'),
               'matched_terms' => fn (Blueprint $t) => $t->json('matched_terms')->nullable(),
         ];

         foreach ($columns as $name => $add) {
               if (! Schema::hasColumn(self::TABLE, $name)) {
                  Schema::table(self::TABLE, fn (Blueprint $t) => $add($t));
               }
         }

         $indexes = collect(Schema::getIndexes(self::TABLE))->pluck('name')->all();

         // One row per rank per OSY. Added first so the old index can be dropped safely.
         if (! in_array('osy_rank_unique', $indexes, true)) {
               Schema::table(self::TABLE, function (Blueprint $table) {
                  $table->unique(['osy_profile_id', 'rank'], 'osy_rank_unique');
               });
         }

         // The old one-row-per-program rule would block two suggestions pointing at the same program
         if (in_array('osy_training_unique', $indexes, true)) {
               Schema::table(self::TABLE, function (Blueprint $table) {
                  $table->dropUnique('osy_training_unique');
               });
         }
      }

      public function down(): void
      {
         $indexes = collect(Schema::getIndexes(self::TABLE))->pluck('name')->all();

         if (! in_array('osy_training_unique', $indexes, true)) {
               Schema::table(self::TABLE, function (Blueprint $table) {
                  $table->unique(['osy_profile_id', 'training_program_id'], 'osy_training_unique');
               });
         }

         if (in_array('osy_rank_unique', $indexes, true)) {
               Schema::table(self::TABLE, function (Blueprint $table) {
                  $table->dropUnique('osy_rank_unique');
               });
         }

         $existing = array_values(array_filter(
               ['course_name', 'career_path', 'is_available', 'mode', 'based_on', 'relation', 'matched_terms'],
               fn ($c) => Schema::hasColumn(self::TABLE, $c)
         ));

         if ($existing) {
               Schema::table(self::TABLE, fn (Blueprint $t) => $t->dropColumn($existing));
         }
      }
   };