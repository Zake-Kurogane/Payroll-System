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
