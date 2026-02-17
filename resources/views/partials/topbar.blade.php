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
                <a href="#" class="user-dropdown__item" role="menuitem" id="editProfileBtn">
                    <span class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="menu-icon__svg">
                            <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z" />
                        </svg>
                    </span>
                    <span>Edit Profile</span>
                </a>
                <a href="{{ route('settings') }}" class="user-dropdown__item" role="menuitem">
                    <span class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="menu-icon__svg">
                            <path d="M12 8a4 4 0 1 0 4 4 4 4 0 0 0-4-4zm9.4 4a7.52 7.52 0 0 0-.12-1l2-1.55-2-3.46-2.35.74a7.65 7.65 0 0 0-1.72-1L15 2h-4l-.21 2.73a7.65 7.65 0 0 0-1.72 1L6.72 5l-2 3.46L6.72 10a7.52 7.52 0 0 0-.12 1 7.52 7.52 0 0 0 .12 1l-2 1.55 2 3.46 2.35-.74a7.65 7.65 0 0 0 1.72 1L11 22h4l.21-2.73a7.65 7.65 0 0 0 1.72-1l2.35.74 2-3.46-2-1.55a7.52 7.52 0 0 0 .12-1z" />
                        </svg>
                    </span>
                    <span>Settings</span>
                </a>

                <div class="user-dropdown__divider" aria-hidden="true"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="user-dropdown__item user-dropdown__item--btn" role="menuitem">
                        <span class="menu-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="menu-icon__svg">
                                <path d="M10 17l1.41-1.41L8.83 13H20v-2H8.83l2.58-2.59L10 7l-5 5 5 5zM4 4h8V2H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8v-2H4z" />
                            </svg>
                        </span>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
