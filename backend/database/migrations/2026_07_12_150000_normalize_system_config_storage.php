<?php

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    public function up(): void
    {
        $encryption = new LaravelConfigEncryption;

        SystemConfigModel::query()->each(function (SystemConfigModel $model) use ($encryption): void {
            if (! $model->is_sensitive) {
                try {
                    $plain = Crypt::decryptString($model->value);
                    $model->update(['value' => $plain]);
                } catch (DecryptException) {
                    // Already plaintext.
                }

                return;
            }

            try {
                Crypt::decryptString($model->value);
            } catch (DecryptException) {
                $model->update(['value' => $encryption->encrypt($model->value)]);
            }
        });
    }

    public function down(): void
    {
        // Irreversible: cannot know which plaintext values were migrated from ciphertext.
    }
};
