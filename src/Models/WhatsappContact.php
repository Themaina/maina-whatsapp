<?php

namespace Maina888\Whatsapp\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Models\BaseModel;
use Botble\Ecommerce\Models\Customer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $phone
 * @property string|null $wa_id
 * @property string|null $name
 * @property int|null $customer_id
 * @property bool $opted_out
 * @property Carbon|null $opted_out_at
 * @property Carbon|null $last_inbound_at
 * @property Carbon|null $last_outbound_at
 */
class WhatsappContact extends BaseModel
{
    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'phone',
        'wa_id',
        'name',
        'customer_id',
        'opted_out',
        'opted_out_at',
        'last_inbound_at',
        'last_outbound_at',
    ];

    protected $casts = [
        'name' => SafeContent::class,
        'opted_out' => 'bool',
        'opted_out_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'contact_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    /**
     * True while the 24-hour customer service window (opened by the customer's last message) is open.
     */
    public function isWithinWindow(?Carbon $at = null): bool
    {
        if (! $this->last_inbound_at) {
            return false;
        }

        $hours = (int) config('plugins.whatsapp.general.window_hours', 24);

        return $this->last_inbound_at->copy()->addHours($hours)->greaterThan($at ?: Carbon::now());
    }

    public function windowClosesAt(): ?Carbon
    {
        return $this->last_inbound_at?->copy()->addHours((int) config('plugins.whatsapp.general.window_hours', 24));
    }
}
