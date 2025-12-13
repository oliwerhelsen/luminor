<?php

declare(strict_types=1);

use Luminor\DDD\Database\Migrations\Migration;
use Luminor\DDD\Database\Schema\Blueprint;
use Luminor\DDD\Database\Schema\Schema;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_id', 36);
            $table->string('aggregate_type');
            $table->integer('version');
            $table->text('state');
            $table->timestamp('created_at');

            $table->unique(['aggregate_id', 'version']);
            $table->index('aggregate_type');
            $table->index('created_at');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('snapshots');
    }
};
