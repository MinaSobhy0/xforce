@props([
    'navigation',
])

@php
    $openSidebarClasses = 'fi-sidebar-open translate-x-0 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 rtl:-translate-x-0';
    $isRtl = __('filament-panels::layout.direction') === 'rtl';
    $panelId = filament()->getCurrentPanel()?->getId();

    // Quick Access items configuration - only for tenant panel
    $quickAccessItems = [];
    if ($panelId === 'tenant') {
        $user = auth()->user();

        // Helper to check permission (mimics ChecksResourcePermissions logic)
        $canAccess = function(string $permission) use ($user) {
            if (!$user) {
                return false;
            }

            // Super admin and key roles have full access
            if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
                return true;
            }

            // Check the specific permission
            if ($user->can($permission)) {
                return true;
            }

            // If permission doesn't exist yet, allow access (fallback)
            $permissionExists = \Spatie\Permission\Models\Permission::where('name', $permission)
                ->where('guard_name', 'web')
                ->exists();

            return !$permissionExists;
        };

        // Today (Reception) - requires visits.view permission
        if ($canAccess('visits.view')) {
            $quickAccessItems[] = [
                'label' => __('Today'),
                'icon' => 'heroicon-o-calendar',
                'url' => route('filament.tenant.pages.reception'),
            ];
        }

        // Book - requires appointments.create permission
        if ($canAccess('appointments.create')) {
            $quickAccessItems[] = [
                'label' => __('Book'),
                'icon' => 'heroicon-o-plus-circle',
                'url' => route('filament.tenant.pages.create-booking'),
            ];
        }

        // Patients - requires patients.view permission
        if ($canAccess('patients.view')) {
            $quickAccessItems[] = [
                'label' => __('Patients'),
                'icon' => 'heroicon-o-users',
                'url' => route('filament.tenant.resources.patients.index'),
            ];
        }

        // Calendar - requires appointments.view permission
        if ($canAccess('appointments.view')) {
            $quickAccessItems[] = [
                'label' => __('Calendar'),
                'icon' => 'heroicon-o-calendar-days',
                'url' => route('filament.tenant.pages.calendar'),
            ];
        }
    }
@endphp

<style>
    /* Icon Rail Header */
    .fi-rail-header {
        border-bottom: 1px solid #e5e7eb;
    }

    .dark .fi-rail-header {
        border-bottom: 1px solid #374151;
    }

    /* Logo */
    .fi-rail-logo {
        color: #1f2937;
    }

    .dark .fi-rail-logo {
        color: #f1f5f9;
    }

    /* Icon Rail Styles - Light Mode */
    .fi-sidebar-rail-light {
        background: #ffffff !important;
        border-right: 1px solid #e5e7eb !important;
    }

    .fi-sidebar-rail-light .fi-rail-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 10px 4px;
        border-radius: 10px;
        color: #64748b;
        cursor: pointer;
        border: none;
        background: transparent;
        transition: all 0.2s ease;
    }

    .fi-sidebar-rail-light .fi-rail-btn:hover {
        background: #f1f5f9;
        color: #334155;
    }

    .fi-sidebar-rail-light .fi-rail-btn.active {
        background: #eef2ff;
        color: #4f46e5;
        box-shadow: inset 3px 0 0 #6366f1;
    }

    .fi-sidebar-rail-light .fi-rail-btn-icon {
        width: 20px;
        height: 20px;
        margin-bottom: 4px;
    }

    .fi-sidebar-rail-light .fi-rail-btn-label {
        font-size: 8px;
        font-weight: 500;
        text-align: center;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.01em;
        max-width: 100%;
        word-wrap: break-word;
        white-space: normal;
    }


    /* Icon Rail Styles - Dark Mode - Match main system gray */
    .dark .fi-sidebar-rail-light {
        background: #030712 !important;
        border-right: 1px solid #374151 !important;
    }

    .dark .fi-sidebar-rail-light .fi-rail-btn {
        color: #94a3b8;
    }

    .dark .fi-sidebar-rail-light .fi-rail-btn:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #f1f5f9;
    }

    .dark .fi-sidebar-rail-light .fi-rail-btn.active {
        background: rgba(99, 102, 241, 0.15);
        color: #a5b4fc;
        box-shadow: inset 3px 0 0 #6366f1;
    }

    .dark .fi-sidebar-rail-light .fi-rail-logo {
        color: #f1f5f9;
    }

    .fi-rail-footer {
        border-top: 1px solid #e5e7eb;
        padding: 8px 6px;
    }

    .dark .fi-rail-footer {
        border-top: 1px solid #374151;
    }

    /* Menu Panel Styles - Light Mode */
    .fi-sidebar-menu-panel {
        background: #ffffff !important;
        border-right: 1px solid #e5e7eb !important;
    }

    .fi-sidebar-menu-panel .fi-panel-header {
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 16px;
        border-bottom: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .fi-sidebar-menu-panel .fi-panel-title {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    /* Menu Panel Styles - Dark Mode - Match main system gray */
    .dark .fi-sidebar-menu-panel {
        background: #111827 !important;
        border-right: 1px solid #374151 !important;
    }

    .dark .fi-sidebar-menu-panel .fi-panel-header {
        border-bottom: 1px solid #374151 !important;
        background: #111827 !important;
    }

    .dark .fi-sidebar-menu-panel .fi-panel-title {
        color: #f1f5f9;
    }

    .fi-panel-close-btn {
        padding: 8px;
        border-radius: 8px;
        color: #94a3b8;
        cursor: pointer;
        border: none;
        background: transparent;
        transition: all 0.15s ease;
    }

    .fi-panel-close-btn:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .dark .fi-panel-close-btn:hover {
        background: #1f2937 !important;
        color: #f9fafb !important;
    }

    /* Quick Access Section */
    .fi-quick-access-section {
        border-bottom: 1px solid #e5e7eb;
        background: #fafafa;
    }

    .dark .fi-quick-access-section {
        border-bottom: 1px solid #374151 !important;
        background: #030712 !important;
    }

    /* Quick Access Grid */
    .fi-quick-access-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 6px;
    }

    .fi-quick-access-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 8px 4px;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .fi-quick-access-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
    }

    .fi-quick-access-btn .fi-qa-icon {
        width: 18px;
        height: 18px;
        color: #64748b;
        margin-bottom: 2px;
    }

    .fi-quick-access-btn:hover .fi-qa-icon {
        color: #6366f1;
    }

    .fi-quick-access-btn .fi-qa-label {
        font-size: 10px;
        font-weight: 500;
        color: #475569;
    }

    .fi-quick-access-btn:hover .fi-qa-label {
        color: #1f2937;
    }

    /* Quick Access - Dark Mode */
    .dark .fi-quick-access-btn {
        background: #1f2937 !important;
        border-color: #374151 !important;
    }

    .dark .fi-quick-access-btn:hover {
        background: #374151 !important;
        border-color: #4b5563 !important;
    }

    .dark .fi-quick-access-btn .fi-qa-icon {
        color: #94a3b8;
    }

    .dark .fi-quick-access-btn:hover .fi-qa-icon {
        color: #a5b4fc;
    }

    .dark .fi-quick-access-btn .fi-qa-label {
        color: #cbd5e1;
    }

    .dark .fi-quick-access-btn:hover .fi-qa-label {
        color: #f1f5f9;
    }

    /* Menu Items */
    .fi-menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        color: #475569;
        text-decoration: none;
        transition: all 0.15s ease;
        margin-bottom: 2px;
    }

    .fi-menu-item:hover {
        background: #f1f5f9;
        color: #1f2937;
    }

    .fi-menu-item.active {
        background: #eef2ff;
        color: #4f46e5;
        box-shadow: inset 3px 0 0 #6366f1;
    }

    .fi-menu-item .fi-menu-icon {
        width: 20px;
        height: 20px;
        color: #94a3b8;
        flex-shrink: 0;
    }

    .fi-menu-item:hover .fi-menu-icon {
        color: #64748b;
    }

    .fi-menu-item.active .fi-menu-icon {
        color: #6366f1;
    }

    .fi-menu-item .fi-menu-badge {
        margin-left: auto;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        background: #f1f5f9;
        color: #64748b;
    }

    .fi-menu-item.active .fi-menu-badge {
        background: #e0e7ff;
        color: #4f46e5;
    }

    /* Menu Items - Dark Mode */
    .dark .fi-menu-item {
        color: #cbd5e1;
    }

    .dark .fi-menu-item:hover {
        background: #1f2937 !important;
        color: #f9fafb !important;
    }

    .dark .fi-menu-item.active {
        background: rgba(99, 102, 241, 0.15);
        color: #a5b4fc;
    }

    .dark .fi-menu-item .fi-menu-icon {
        color: #64748b;
    }

    .dark .fi-menu-item:hover .fi-menu-icon {
        color: #94a3b8;
    }

    .dark .fi-menu-item.active .fi-menu-icon {
        color: #a5b4fc;
    }

    .dark .fi-menu-item .fi-menu-badge {
        background: #1f2937 !important;
        color: #9ca3af !important;
    }

    .dark .fi-menu-item.active .fi-menu-badge {
        background: rgba(99, 102, 241, 0.2);
        color: #a5b4fc;
    }

    /* Submenu List */
    .fi-submenu-list {
        margin: 4px 0 4px 32px;
        padding-left: 12px;
        border-left: 2px solid #e5e7eb;
        list-style: none;
    }

    .dark .fi-submenu-list {
        border-left-color: #4b5563;
    }

    /* Scrollbar styling */
    .fi-sidebar-panel-nav {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .fi-sidebar-panel-nav::-webkit-scrollbar {
        width: 6px;
    }

    .fi-sidebar-panel-nav::-webkit-scrollbar-track {
        background: transparent;
    }

    .fi-sidebar-panel-nav::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .fi-sidebar-panel-nav::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

{{-- format-ignore-start --}}
<aside
    x-data="{
        activeGroup: localStorage.getItem('sidebar_active_group') || null,
        isPanelCollapsed: localStorage.getItem('sidebar_panel_collapsed') === 'true',
        init() {
            this.activeGroup = localStorage.getItem('sidebar_active_group') || null;
            this.isPanelCollapsed = localStorage.getItem('sidebar_panel_collapsed') === 'true';
        },
        setActiveGroup(group) {
            if (this.activeGroup === group) {
                this.activeGroup = null;
                localStorage.removeItem('sidebar_active_group');
            } else {
                this.activeGroup = group;
                localStorage.setItem('sidebar_active_group', group);
                this.isPanelCollapsed = false;
                localStorage.setItem('sidebar_panel_collapsed', 'false');
            }
        },
        closeGroup() {
            this.activeGroup = null;
            localStorage.removeItem('sidebar_active_group');
        },
        togglePanel() {
            this.isPanelCollapsed = !this.isPanelCollapsed;
            localStorage.setItem('sidebar_panel_collapsed', this.isPanelCollapsed);
            if (this.isPanelCollapsed) {
                this.activeGroup = null;
                localStorage.removeItem('sidebar_active_group');
            }
        },
        isGroupActive(group) {
            return this.activeGroup === group;
        }
    }"
    x-cloak="-lg"
    x-bind:class="$store.sidebar.isOpen ? @js($openSidebarClasses) : '-translate-x-full rtl:translate-x-full lg:translate-x-0'"
    {{
        $attributes->class([
            'fi-sidebar fixed inset-y-0 start-0 z-30 flex h-screen content-start bg-transparent transition-all dark:bg-transparent lg:z-0 lg:sticky lg:shadow-none lg:ring-0 lg:transition-none',
        ])
    }}
>
    <div style="height: 100vh; display: flex; overflow: hidden;">
        {{-- Icon Rail (Left Sidebar) - LIGHT --}}
        <div class="fi-sidebar-rail-light" style="width: 85px; height: 100%; display: flex; flex-direction: column; flex-shrink: 0;">
            {{-- Logo Area --}}
            <header class="fi-rail-header" style="height: 64px; display: flex; align-items: center; justify-content: center; padding: 4px;">
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }} style="display: flex; align-items: center; justify-content: center;">
                        @php
                            $logoLight = \App\Models\PlatformSetting::get('platform_logo');
                            $logoDark = \App\Models\PlatformSetting::get('platform_logo_dark');
                        @endphp
                        @if($logoLight || $logoDark)
                            <div x-data="{ isDark: document.documentElement.classList.contains('dark') }"
                                 x-init="
                                    const observer = new MutationObserver(() => {
                                        isDark = document.documentElement.classList.contains('dark');
                                    });
                                    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                                 ">
                                {{-- Light mode logo --}}
                                @if($logoLight)
                                    <img x-show="!isDark" src="{{ asset('storage/' . $logoLight) }}" alt="Logo" style="height: 32px; width: auto; object-fit: contain;" />
                                @endif
                                {{-- Dark mode logo --}}
                                @if($logoDark)
                                    <img x-show="isDark" src="{{ asset('storage/' . $logoDark) }}" alt="Logo" style="height: 32px; width: auto; object-fit: contain;" />
                                @elseif($logoLight)
                                    {{-- Fallback: use light logo with invert filter in dark mode --}}
                                    <img x-show="isDark" src="{{ asset('storage/' . $logoLight) }}" alt="Logo" style="height: 32px; width: auto; object-fit: contain; filter: brightness(0) invert(1);" />
                                @endif
                            </div>
                        @else
                            <div class="fi-rail-logo" style="font-size: 18px; font-weight: 700; letter-spacing: -0.02em;">
                                <span style="color: #6366f1;">X</span>Linic
                            </div>
                        @endif
                    </a>
                @endif
            </header>

            {{-- Navigation Icons --}}
            <nav style="flex: 1; overflow-y: auto; padding: 12px 6px;">
                <ul style="display: flex; flex-direction: column; gap: 4px; list-style: none; margin: 0; padding: 0;">
                    @foreach ($navigation as $group)
                        @php
                            $groupLabel = $group->getLabel();
                            $groupIcon = $group->getIcon();
                        @endphp
                        <li>
                            <button
                                type="button"
                                x-on:click="setActiveGroup('{{ $groupLabel }}')"
                                :class="isGroupActive('{{ $groupLabel }}') ? 'fi-rail-btn active' : 'fi-rail-btn'"
                                title="{{ $groupLabel }}"
                            >
                                @if($groupIcon)
                                    <x-filament::icon
                                        :icon="$groupIcon"
                                        class="fi-rail-btn-icon"
                                    />
                                @else
                                    <x-filament::icon
                                        icon="heroicon-o-folder"
                                        class="fi-rail-btn-icon"
                                    />
                                @endif
                                <span class="fi-rail-btn-label">{{ $groupLabel }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Logout --}}
            <div class="fi-rail-footer">
                <form action="{{ filament()->getLogoutUrl() }}" method="post" style="width: 100%;">
                    @csrf
                    <button type="submit" class="fi-rail-btn" style="width: 100%;">
                        <x-filament::icon icon="heroicon-o-arrow-left-on-rectangle" class="fi-rail-btn-icon" />
                        <span class="fi-rail-btn-label">Logout</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Menu Panel (Right Sidebar) - LIGHT --}}
        <div
            x-show="activeGroup !== null && !isPanelCollapsed"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 -translate-x-4"
            class="fi-sidebar-menu-panel"
            style="width: 260px; height: 100vh; display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden; box-shadow: 4px 0 15px rgba(0, 0, 0, 0.05);"
        >
            {{-- Panel Header --}}
            <header class="fi-panel-header">
                <span class="fi-panel-title" x-text="activeGroup"></span>
                <button
                    type="button"
                    x-on:click="togglePanel()"
                    class="fi-panel-close-btn"
                    title="Collapse"
                >
                    <x-filament::icon icon="heroicon-o-chevron-left" style="width: 18px; height: 18px;" />
                </button>
            </header>

            {{-- Quick Access Section --}}
            @if(count($quickAccessItems) > 0)
                <div class="fi-quick-access-section" style="padding: 10px 12px;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                        <x-filament::icon icon="heroicon-o-bolt" style="width: 12px; height: 12px; color: #94a3b8;" />
                        <span style="font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Quick Access</span>
                    </div>
                    <div class="fi-quick-access-grid">
                        @foreach($quickAccessItems as $item)
                            <a href="{{ $item['url'] }}" class="fi-quick-access-btn">
                                <x-filament::icon :icon="$item['icon']" class="fi-qa-icon" />
                                <span class="fi-qa-label">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Menu Items --}}
            <nav class="fi-sidebar-panel-nav" style="overflow-y: auto; padding: 12px; padding-bottom: 20px; height: calc(100vh - 220px);">
                @foreach ($navigation as $group)
                    <ul
                        x-show="activeGroup === '{{ $group->getLabel() }}'"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        style="list-style: none; margin: 0; padding: 0;"
                    >
                        @foreach ($group->getItems() as $item)
                            @php
                                $itemIsActive = $item->isActive();
                                $hasChildren = count($item->getChildItems()) > 0;
                            @endphp
                            <li x-data="{ expanded: {{ $itemIsActive || collect($item->getChildItems())->contains(fn($child) => $child->isActive()) ? 'true' : 'false' }} }">
                                @if($hasChildren)
                                    {{-- Parent item with children --}}
                                    <button
                                        type="button"
                                        x-on:click="expanded = !expanded"
                                        class="fi-menu-item {{ $itemIsActive ? 'active' : '' }}"
                                        style="width: 100%; cursor: pointer; border: none; background: transparent; text-align: left;"
                                    >
                                        @if($icon = $item->getIcon())
                                            <x-filament::icon :icon="$icon" class="fi-menu-icon" />
                                        @endif
                                        <span style="flex: 1;">{{ $item->getLabel() }}</span>
                                        <x-filament::icon
                                            icon="heroicon-o-chevron-down"
                                            style="width: 16px; height: 16px; color: #94a3b8; transition: transform 0.2s ease;"
                                            x-bind:style="expanded ? 'transform: rotate(180deg);' : ''"
                                        />
                                    </button>

                                    {{-- Children --}}
                                    <ul
                                        x-show="expanded"
                                        x-collapse
                                        class="fi-submenu-list"
                                    >
                                        @foreach ($item->getChildItems() as $childItem)
                                            @php $childIsActive = $childItem->isActive(); @endphp
                                            <li>
                                                <a
                                                    href="{{ $childItem->getUrl() }}"
                                                    @if ($childItem->shouldOpenUrlInNewTab()) target="_blank" @endif
                                                    class="fi-menu-item {{ $childIsActive ? 'active' : '' }}"
                                                    style="font-size: 13px; padding: 8px 12px;"
                                                >
                                                    <span>{{ $childItem->getLabel() }}</span>
                                                    @if($badge = $childItem->getBadge())
                                                        <span class="fi-menu-badge">{{ $badge }}</span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    {{-- Simple item without children --}}
                                    <a
                                        href="{{ $item->getUrl() }}"
                                        @if ($item->shouldOpenUrlInNewTab()) target="_blank" @endif
                                        class="fi-menu-item {{ $itemIsActive ? 'active' : '' }}"
                                    >
                                        @if($icon = $item->getIcon())
                                            <x-filament::icon :icon="$icon" class="fi-menu-icon" />
                                        @endif
                                        <span>{{ $item->getLabel() }}</span>
                                        @if($badge = $item->getBadge())
                                            <span class="fi-menu-badge">{{ $badge }}</span>
                                        @endif
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>
        </div>
    </div>
</aside>
{{-- format-ignore-end --}}
