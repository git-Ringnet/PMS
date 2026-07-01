<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || true) {
            URL::forceScheme('https');
        }

        // Đăng ký Event Listener toàn cục để tự động bắt các thay đổi dữ liệu của Eloquent
        \Illuminate\Support\Facades\Event::listen('eloquent.*', function ($eventName, array $data) {
            if (app()->runningInConsole()) {
                return;
            }

            if (!str_contains($eventName, 'eloquent.created:') && 
                !str_contains($eventName, 'eloquent.updated:') && 
                !str_contains($eventName, 'eloquent.deleted:')) {
                return;
            }

            $model = $data[0] ?? null;
            if (!$model || !($model instanceof \Illuminate\Database\Eloquent\Model)) {
                return;
            }

            // Bỏ qua ActivityLog để tránh lặp vô hạn
            if ($model instanceof \App\Models\ActivityLog) {
                return;
            }

            $request = request();
            if (!$request) {
                return;
            }

            $action = '';
            if (str_contains($eventName, '.created:')) {
                $action = 'created';
            } elseif (str_contains($eventName, '.updated:')) {
                $action = 'updated';
            } elseif (str_contains($eventName, '.deleted:')) {
                $action = 'deleted';
            }

            $targetId = $model->getKey();
            $targetType = class_basename($model);
            
            // Tìm nhãn đại diện cho Model
            $targetLabel = null;
            foreach (['name', 'title', 'code', 'username', 'label', 'display_name', 'room_code', 'registration_code'] as $field) {
                if (isset($model->{$field})) {
                    $targetLabel = $model->{$field};
                    break;
                }
            }

            $oldValues = null;
            $newValues = null;
            $skipFields = ['updated_at', 'created_at', 'remember_token', 'password'];

            if ($action === 'created') {
                $newValues = [];
                foreach ($model->getAttributes() as $key => $val) {
                    if (in_array($key, $skipFields)) continue;
                    $newValues[$key] = $val;
                }
            } elseif ($action === 'updated') {
                $changes = $model->getChanges();
                $original = $model->getOriginal();
                $oldValues = [];
                $newValues = [];
                foreach ($changes as $key => $newVal) {
                    if (in_array($key, $skipFields)) continue;
                    $oldVal = $original[$key] ?? null;
                    $isDifferent = (is_array($oldVal) || is_array($newVal))
                        ? (json_encode($oldVal) !== json_encode($newVal))
                        : ((string)$oldVal !== (string)$newVal);

                    if ($isDifferent) {
                        $oldValues[$key] = $oldVal;
                        $newValues[$key] = $newVal;
                    }
                }
                if (empty($newValues)) {
                    return;
                }
            } elseif ($action === 'deleted') {
                $oldValues = [];
                foreach ($model->getOriginal() as $key => $val) {
                    if (in_array($key, $skipFields)) continue;
                    $oldValues[$key] = $val;
                }
            }

            $request->attributes->set('_last_model_change', [
                'target_id' => $targetId,
                'target_type' => $targetType,
                'target_label' => $targetLabel,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);
        });
    }
}
