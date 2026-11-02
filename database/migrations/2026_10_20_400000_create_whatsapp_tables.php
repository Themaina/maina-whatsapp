<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_contacts')) {
            Schema::create('whatsapp_contacts', function (Blueprint $table): void {
                $table->id();
                $table->string('phone', 20)->unique();
                $table->string('wa_id', 32)->nullable();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->boolean('opted_out')->default(false);
                $table->timestamp('opted_out_at')->nullable();
                $table->timestamp('last_inbound_at')->nullable();
                $table->timestamp('last_outbound_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('direction', 3)->default('out')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->string('phone', 20)->index();
                $table->string('to', 32)->nullable();
                $table->string('from', 32)->nullable();
                $table->string('type', 20)->default('template');
                $table->string('template_key', 120)->nullable()->index();
                $table->string('template_name', 512)->nullable();
                $table->string('language', 15)->nullable();
                $table->text('body')->nullable();
                $table->json('payload')->nullable();
                $table->string('status', 20)->default('queued')->index();
                $table->string('provider_message_id')->nullable()->unique();
                $table->string('error_code', 20)->nullable();
                $table->text('error')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->json('context')->nullable();
                $table->nullableMorphs('related');
                $table->unsignedBigInteger('resent_from_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->index(['created_at', 'status']);
            });
        }

        if (! Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('meta_id', 64)->nullable();
                $table->string('name', 255);
                $table->string('language', 15);
                $table->string('status', 30)->nullable();
                $table->string('category', 30)->nullable();
                $table->text('body')->nullable();
                $table->unsignedTinyInteger('body_variables')->default(0);
                $table->boolean('has_url_button')->default(false);
                $table->json('components')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['name', 'language']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_contacts');
    }
};
