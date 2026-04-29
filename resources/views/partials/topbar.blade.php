@php
    $notifications = $topbarNotifications ?? [];
    $notificationCount = count($notifications);
@endphp

<header class="top">
    <div>
        <div class="top__title">WELCOME</div>
        <div class="top__sub">
            {{
                trim(
                    collect([
                        auth()->user()->first_name,
                        auth()->user()->middle_name,
                        auth()->user()->last_name,
                    ])->filter()->implode(' ')
                ) ?: auth()->user()->name
            }}
        </div>
    </div>

    <div class="top__right">
        <div class="notif-menu">
            <button class="notif-btn" type="button" id="notifMenuBtn" aria-haspopup="true" aria-expanded="false"
                aria-label="Notifications">
                <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                    <path
                        d="M12 22a2.4 2.4 0 0 0 2.35-2h-4.7A2.4 2.4 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Z" />
                </svg>
                <span class="notif-btn__badge">{{ $notificationCount }}</span>
            </button>

            <div class="notif-dropdown" id="notifMenu" role="menu" aria-labelledby="notifMenuBtn">
                <div class="notif-dropdown__header">Notifications</div>
                @forelse ($notifications as $notification)
                    @php
                        $notifKey = md5(($notification['message'] ?? '') . '|' . ($notification['target_url'] ?? ''));
                    @endphp
                    <a href="{{ $notification['target_url'] ?? '#' }}"
                        class="notif-dropdown__item notif-dropdown__item--active"
                        data-notif-key="{{ $notifKey }}"
                        role="menuitem"
                        @if (empty($notification['target_url'])) aria-disabled="true" @endif>
                        {{ $notification['message'] ?? '' }}
                    </a>
                @empty
                    <div class="notif-dropdown__item notif-dropdown__item--empty" role="menuitem">
                        No new notifications.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="user-menu">
            <button class="pill-user" type="button" id="userMenuBtn" aria-haspopup="true"
                aria-expanded="false">
                <span class="pill-user__name">
                    {{
                        trim(
                            collect([
                                auth()->user()->first_name,
                                auth()->user()->middle_name,
                                auth()->user()->last_name,
                            ])->filter()->implode(' ')
                        ) ?: auth()->user()->name
                    }}
                </span>
                <span class="pill-user__avatar" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path
                            d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z" />
                    </svg>
                </span>
            </button>

            <div class="user-dropdown" id="userMenu" role="menu" aria-labelledby="userMenuBtn">
                @can('admin')
                    <a href="#" class="user-dropdown__item" role="menuitem" id="editProfileBtn">
                        <span class="menu-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="menu-icon__svg">
                                <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z" />
                            </svg>
                        </span>
                        <span>Edit Profile</span>
                    </a>
                @endcan
                @can('admin')
                    <a href="{{ route('settings') }}" class="user-dropdown__item" role="menuitem">
                        <span class="menu-icon" aria-hidden="true">
                            <svg viewBox="0 0 16 16" class="menu-icon__svg">
                                <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.46 1.46 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.46 1.46 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.46 1.46 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.46 1.46 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.46 1.46 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.46 1.46 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.46 1.46 0 0 1-2.105-.872l-.1-.34zM8 10.2A2.2 2.2 0 1 0 8 5.8a2.2 2.2 0 0 0 0 4.4z" />
                            </svg>
                        </span>
                        <span>Settings</span>
                    </a>
                @endcan

                <div class="user-dropdown__divider" aria-hidden="true"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="user-dropdown__item user-dropdown__item--btn" role="menuitem">
                        <span class="menu-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="menu-icon__svg">
                                <path d="M16 17v-3H8v-4h8V7l5 5-5 5ZM3 3h9v2H5v14h7v2H3V3Z" />
                            </svg>
                        </span>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<style>
    .top__right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notif-menu {
        position: relative;
    }

    .notif-btn {
        position: relative;
        width: 42px;
        height: 42px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        display: grid;
        place-items: center;
        cursor: pointer;
    }

    .notif-btn .ico {
        width: 18px;
        height: 18px;
        fill: currentColor;
    }

    .notif-btn__badge {
        position: absolute;
        top: -4px;
        right: -2px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .notif-dropdown {
        position: absolute;
        right: 0;
        top: calc(100% + 10px);
        width: min(420px, 90vw);
        max-height: 420px;
        overflow: auto;
        background: #fff;
        border: 1px solid rgba(17, 24, 39, 0.12);
        border-radius: 14px;
        box-shadow: 0 18px 28px rgba(17, 24, 39, 0.14);
        padding: 8px;
        display: none;
        z-index: 9999;
    }

    .notif-dropdown.is-open {
        display: block;
    }

    .notif-dropdown__header {
        font-size: 12px;
        font-weight: 800;
        color: #6b7280;
        padding: 6px 8px 10px;
        border-bottom: 1px solid rgba(17, 24, 39, 0.1);
        margin-bottom: 4px;
    }

    .notif-dropdown__item {
        display: block;
        padding: 10px 12px;
        border-radius: 10px;
        font-size: 13px;
        line-height: 1.35;
        font-weight: 700;
        color: #111827;
        text-decoration: none;
        background: rgba(107, 114, 128, 0.1);
        margin-bottom: 8px;
    }

    .notif-dropdown__item:hover {
        filter: brightness(0.98);
    }

    .notif-dropdown__item--empty {
        background: rgba(107, 114, 128, 0.08);
        color: #6b7280;
        margin-bottom: 0;
    }

    .notif-dropdown__item:last-child {
        margin-bottom: 0;
    }

    .notif-dropdown__item--active {
        background: rgba(156, 29, 60, 0.18);
    }
</style>
