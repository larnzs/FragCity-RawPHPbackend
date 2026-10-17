<?php

namespace Flute\Admin\Packages\NotificationTemplates\Screens;

use Cycle\Database\Injection\Fragment;
use Cycle\Database\Injection\Parameter;
use Flute\Admin\Platform\Actions\Button;
use Flute\Admin\Platform\Fields\CheckBox;
use Flute\Admin\Platform\Fields\Input;
use Flute\Admin\Platform\Fields\Select;
use Flute\Admin\Platform\Fields\TextArea;
use Flute\Admin\Platform\Layouts\LayoutFactory;
use Flute\Admin\Platform\Screen;
use Flute\Admin\Platform\Support\Color;
use Flute\Core\Database\Entities\User;
use Flute\Core\Modules\Notifications\Services\NotificationService;
use Flute\Core\Modules\Notifications\Services\NotificationTemplateService;
use Throwable;

class NotificationBroadcastScreen extends Screen
{
    private const RECIPIENT_BATCH_SIZE = 500;

    public ?string $name = null;

    public ?string $description = null;

    public ?string $permission = 'admin.notifications';

    public string $target = 'all';

    public function mount(): void
    {
        $this->name = __('admin-notifications.broadcast.title');
        $this->description = __('admin-notifications.broadcast.description');

        $target = request()->input('target', $this->target);
        $this->target = is_array($target) ? $target[0] ?? 'all' : $target;

        breadcrumb()->add(__('def.admin_panel'), url('/admin'))->add(__('admin-notifications.broadcast.title'));
    }

    public function commandBar(): array
    {
        return [
            Button::make(__('def.send'))
                ->type(Color::PRIMARY)
                ->icon('ph.bold.paper-plane-tilt-bold')
                ->confirm(__('admin-notifications.broadcast.confirm_send'), 'info')
                ->method('send'),
        ];
    }

    public function layout(): array
    {
        return [
            // Recipients block
            LayoutFactory::block(array_filter([
                LayoutFactory::field(
                    Select::make('target')
                        ->options([
                            'all' => __('admin-notifications.broadcast.target_all'),
                            'roles' => __('admin-notifications.broadcast.target_roles'),
                            'users' => __('admin-notifications.broadcast.target_users'),
                        ])
                        ->aligned()
                        ->value($this->target)
                        ->yoyo(),
                )->label(__('admin-notifications.broadcast.target')),

                $this->target === 'roles'
                    ? LayoutFactory::field(
                        Select::make('roles')
                            ->fromDatabase('roles', 'name', 'id', ['name', 'id'])
                            ->multiple(true)
                            ->placeholder(__('admin-notifications.broadcast.roles')),
                    )
                        ->label(__('admin-notifications.broadcast.roles'))
                        ->required()
                    : null,

                $this->target === 'users'
                    ? LayoutFactory::field(
                        Select::make('users')
                            ->fromDatabase('users', 'name', 'id', ['name', 'id', 'login'])
                            ->multiple(true)
                            ->placeholder(__('admin-notifications.broadcast.users')),
                    )
                        ->label(__('admin-notifications.broadcast.users'))
                        ->required()
                    : null,
                LayoutFactory::columns($this->buildChannelFields()),
            ]))
                ->title(__('admin-notifications.broadcast.blocks.recipients'))
                ->description(__('admin-notifications.broadcast.blocks.recipients_description'))
                ->addClass('mb-3'),

            // Content + Preview columns
            LayoutFactory::columns([
                // Left: Form
                LayoutFactory::blank([
                    LayoutFactory::block([
                        LayoutFactory::field(
                            Input::make('title')
                                ->required()
                                ->placeholder(__('admin-notifications.broadcast.notification_title')),
                        )
                            ->label(__('admin-notifications.broadcast.notification_title'))
                            ->required(),

                        LayoutFactory::field(
                            TextArea::make('content')
                                ->required()
                                ->rows(4)
                                ->placeholder(__('admin-notifications.broadcast.notification_content')),
                        )
                            ->label(__('admin-notifications.broadcast.notification_content'))
                            ->required(),

                        LayoutFactory::field(
                            Input::make('icon')->type('icon')->placeholder('ph.bold.bell-bold'),
                        )->label(__('admin-notifications.broadcast.notification_icon')),

                        LayoutFactory::field(Input::make('url')->placeholder('https://'))->label(__(
                            'admin-notifications.broadcast.notification_url',
                        )),
                    ])->title(__('admin-notifications.broadcast.blocks.content'))->description(__(
                        'admin-notifications.broadcast.blocks.content_description',
                    )),
                ]),

                // Right: Preview
                LayoutFactory::blank([
                    LayoutFactory::view('admin-notifications::partials.broadcast-preview'),
                ]),
            ]),
        ];
    }

    public function send(): void
    {
        @ignore_user_abort(true);
        @set_time_limit(0);

        $target = request()->input('target', 'all');
        if (is_array($target)) {
            $target = $target[0] ?? 'all';
        }
        $target = is_string($target) ? $target : 'all';

        $title = trim((string) request()->input('title', ''));
        $content = trim((string) request()->input('content', ''));
        $icon = trim((string) request()->input('icon', '')) ?: 'ph.bold.bell-bold';
        $url = $this->normalizeUrl(request()->input('url'));

        if ($url === false) {
            $this->flashMessage(__('admin-notifications.broadcast.invalid_url'), 'error');

            return;
        }

        $channels = [];
        if (request()->input('channel_inapp')) {
            $channels[] = 'inapp';
        }
        if (request()->input('channel_email')) {
            $channels[] = 'email';
        }
        if (request()->input('channel_push')) {
            $channels[] = 'push';
        }

        if (empty($channels)) {
            $channels = ['inapp'];
        }

        $validation = $this->validate([
            'title' => ['required', 'string'],
            'content' => ['required', 'string'],
        ], request()->input());

        if (!$validation) {
            return;
        }

        $notificationService = app(NotificationService::class);
        $count = 0;
        $foundRecipients = false;

        $hasEmail = in_array('email', $channels, true) && function_exists('email');
        $hasPush = in_array('push', $channels, true);
        $hasInApp = in_array('inapp', $channels, true);

        $pushService = null;
        if ($hasPush) {
            try {
                $pushService = app('push.service');
            } catch (Throwable) {
                $hasPush = false;
            }
        }

        if (!$hasInApp && !$hasEmail && !$hasPush) {
            $this->flashMessage(__('admin-notifications.broadcast.no_available_channels'), 'error');

            return;
        }

        foreach ($this->resolveRecipientIdBatches($target) as $userIds) {
            if (empty($userIds)) {
                continue;
            }

            $foundRecipients = true;
            $countedUserIds = [];

            try {
                if ($hasInApp) {
                    $sentInApp = $notificationService->createTextNotificationsForUserIds(
                        $userIds,
                        $title,
                        $content,
                        $icon,
                        $url,
                    );
                    foreach ($userIds as $userId) {
                        $countedUserIds[$userId] = true;
                    }
                    if ($sentInApp !== count($userIds)) {
                        logs()->warning('Notification broadcast inserted fewer in-app notifications than expected', [
                            'expected' => count($userIds),
                            'inserted' => $sentInApp,
                        ]);
                    }
                }
            } catch (Throwable $e) {
                logs()->error($e);
            }

            if ($hasEmail || $hasPush && $pushService) {
                $users = $this->fetchUsersByIds($userIds);

                foreach ($users as $user) {
                    $sent = false;

                    if ($hasEmail) {
                        try {
                            $sent = $this->sendEmailNotification($user, $title, $content) || $sent;
                        } catch (Throwable $e) {
                            logs()->error($e);
                        }
                    }

                    if ($hasPush && $pushService) {
                        try {
                            $pushService->sendToUser($user, $title, $content, $icon, $url);
                            $sent = true;
                        } catch (Throwable $e) {
                            logs()->error($e);
                        }
                    }

                    if ($sent) {
                        $countedUserIds[$user->id] = true;
                    }
                }

                unset($users);
                orm()->getHeap()->clean();
            }

            $count += count($countedUserIds);
        }

        if (!$foundRecipients) {
            $this->flashMessage(__('admin-notifications.broadcast.no_recipients'), 'error');

            return;
        }

        logs()->info('Notification broadcast completed', [
            'target' => $target,
            'channels' => $channels,
            'count' => $count,
        ]);

        $this->flashMessage(__('admin-notifications.broadcast.sent', ['count' => $count]));
    }

    protected function sendEmailNotification(User $user, string $title, string $content): bool
    {
        if (!function_exists('email') || !$user->email) {
            return false;
        }

        email()->send(
            $user->email,
            strip_tags($title),
            view('notifications::emails.notification', [
                'title' => $title,
                'content' => $content,
                'components' => [],
                'user' => $user,
            ]),
        );

        return true;
    }

    protected function buildChannelFields(): array
    {
        $channels = app(NotificationTemplateService::class)->getChannels();
        $fields = [];

        foreach ($channels as $key => $channel) {
            if (!$channel['enabled'] && $key !== 'inapp') {
                continue;
            }

            if ($key === 'telegram') {
                continue;
            }

            $fields[] = LayoutFactory::blank([
                LayoutFactory::field(
                    CheckBox::make('channel_' . $key)
                        ->label($channel['name'])
                        ->checked($key === 'inapp')
                        ->sendTrueOrFalse(),
                ),
            ]);
        }

        return $fields;
    }

    /**
     * @return iterable<array<int,int>>
     */
    protected function resolveRecipientIdBatches(string $target): iterable
    {
        switch ($target) {
            case 'roles':
                $roleIds = $this->normalizeIdList(request()->input('roles', []));
                if (empty($roleIds)) {
                    return;
                }

                $validRoleIds = $this->fetchValidRoleIds($roleIds);
                if (empty($validRoleIds)) {
                    return;
                }

                yield from $this->fetchUserIdBatchesForRoles($validRoleIds);

                return;
            case 'users':
                $userIds = $this->normalizeIdList(request()->input('users', []));
                foreach (array_chunk($userIds, self::RECIPIENT_BATCH_SIZE) as $chunk) {
                    $batch = $this->fetchExistingUserIds($chunk);
                    if (!empty($batch)) {
                        yield $batch;
                    }
                }

                return;

            default:
                yield from $this->fetchAllUserIdBatches();
        }
    }

    /**
     * @param mixed $value
     * @return array<int,int>
     */
    protected function normalizeIdList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($items as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        sort($ids);

        return array_values($ids);
    }

    /**
     * @param array<int,int> $roleIds
     * @return array<int,int>
     */
    protected function fetchValidRoleIds(array $roleIds): array
    {
        $rows = db()->select('id')->from('roles')->where('id', 'IN', new Parameter($roleIds))->fetchAll();

        return $this->extractIds($rows, 'id');
    }

    /**
     * @return iterable<array<int,int>>
     */
    protected function fetchAllUserIdBatches(): iterable
    {
        $lastId = 0;

        do {
            $rows = db()
                ->select('id')
                ->from('users')
                ->where('is_temporary', false)
                ->where('id', '>', $lastId)
                ->orderBy('id', 'ASC')
                ->limit(self::RECIPIENT_BATCH_SIZE)
                ->fetchAll();

            $ids = $this->extractIds($rows, 'id');
            if (!empty($ids)) {
                $lastId = max($ids);
                yield $ids;
            }
        } while (count($ids) === self::RECIPIENT_BATCH_SIZE);
    }

    /**
     * @param array<int,int> $roleIds
     * @return iterable<array<int,int>>
     */
    protected function fetchUserIdBatchesForRoles(array $roleIds): iterable
    {
        $lastId = 0;

        do {
            $rows = db()
                ->select([new Fragment('ur.user_id as user_id')])
                ->from('user_roles as ur')
                ->distinct()
                ->innerJoin('users as u')
                ->on('u.id', 'ur.user_id')
                ->where('u.is_temporary', false)
                ->where('ur.role_id', 'IN', new Parameter($roleIds))
                ->where('ur.user_id', '>', $lastId)
                ->orderBy('ur.user_id', 'ASC')
                ->limit(self::RECIPIENT_BATCH_SIZE)
                ->fetchAll();

            $ids = $this->extractIds($rows, 'user_id');
            if (!empty($ids)) {
                $lastId = max($ids);
                yield $ids;
            }
        } while (count($ids) === self::RECIPIENT_BATCH_SIZE);
    }

    /**
     * @param array<int,int> $userIds
     * @return array<int,int>
     */
    protected function fetchExistingUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = db()
            ->select('id')
            ->from('users')
            ->where('is_temporary', false)
            ->where('id', 'IN', new Parameter($userIds))
            ->orderBy('id', 'ASC')
            ->fetchAll();

        return $this->extractIds($rows, 'id');
    }

    /**
     * @param array<int,int> $userIds
     * @return User[]
     */
    protected function fetchUsersByIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return User::query()
            ->where('id', 'IN', new Parameter($userIds))
            ->orderBy('id', 'ASC')
            ->fetchAll();
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,int>
     */
    protected function extractIds(array $rows, string $column): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $id = (int) ( $row[$column] ?? 0 );
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    protected function normalizeUrl(mixed $url): string|false|null
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (
            is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL)
        ) {
            return $url;
        }

        return false;
    }
}
