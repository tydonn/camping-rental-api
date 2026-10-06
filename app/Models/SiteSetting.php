<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    public const KEY_HERO_IMAGE = 'hero_image';

    public const KEY_MAPS_QUERY = 'maps_query';

    public const KEY_CONTACT = 'contact';

    protected $fillable = [
        'key',
        'value',
        'address',
        'phone',
        'email',
        'hours',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value === null ? $default : $value;
    }

    public static function set(string $key, ?string $value): self
    {
        return static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Baris tunggal yang menampung data kontak situs (alamat, telepon, email, jam operasional).
     */
    public static function contact(): self
    {
        return static::query()->firstOrNew(['key' => self::KEY_CONTACT]);
    }

    /**
     * Nilai seluruh pengaturan yang ditampilkan ke publik,
     * digabung dari baris key-value (hero_image, maps_query) dan kolom pada baris contact.
     */
    public static function payload(): array
    {
        $contact = static::contact();

        return [
            'hero_image' => static::get(self::KEY_HERO_IMAGE),
            'maps_query' => static::get(self::KEY_MAPS_QUERY),
            'address' => $contact->address,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'hours' => $contact->hours,
        ];
    }
}