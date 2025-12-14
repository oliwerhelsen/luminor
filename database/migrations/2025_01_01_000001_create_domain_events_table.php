<?php

declare(strict_types=1);

use Luminor\Database\Migrations\Migration;
use Luminor\Database\Schema\Blueprint;
use Luminor\Database\Schema\Schema;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 36)->unique();
            $table->string('event_type');
            $table->string('aggregate_id', 36)->nullable();
            $table->string('aggregate_type')->nullable();
            $table->integer('version')->default(1);
            $table->text('payload');
            $table->text('metadata')->nullable();
            $table->timestamp('occurred_on');
            $table->timestamp('stored_at');

            $table->index('event_type');
            $table->index('aggregate_id');
            $table->index(['aggregate_id', 'version']);
            $table->index('occurred_on');
            $table->index('stored_at');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('domain_events');
    }
};
