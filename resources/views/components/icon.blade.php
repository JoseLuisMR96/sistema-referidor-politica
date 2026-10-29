    @props(['name', 'class' => 'w-5 h-5'])

    @switch($name)
        @case('home')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l9-9 9 9M4 10v10a1 1 0 001 1h5m4 0h5a1 1 0 001-1V10" />
            </svg>
        @break

        @case('users')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1
                                m4-6a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 100-8" />
            </svg>
        @break

        @case('document-text')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h5l5 5v9a2 2 0 01-2 2z" />
            </svg>
        @break

        @case('link')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5
                                m4.172-2.828a4 4 0 015.656 0l1.5 1.5a4 4 0 01-5.656 5.656" />
            </svg>
        @break

        @case('arrow-down-tray')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l4-4m-4 4l-4-4M4 21h16" />
            </svg>
        @break

        @case('whatsapp')
            <svg class="{{ $class }}" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor"
                    d="M16 2.667c-7.364 0-13.333 5.969-13.333 13.333 0 2.337.615 4.623 1.785 6.644L2.667 29.333l6.828-1.76A13.268 13.268 0 0 0 16 29.333c7.364 0 13.333-5.969 13.333-13.333S23.364 2.667 16 2.667Zm0 24.2c-2.21 0-4.366-.6-6.234-1.734l-.447-.266-4.053 1.046 1.082-3.95-.29-.464A11.486 11.486 0 0 1 4.533 16c0-6.33 5.137-11.467 11.467-11.467S27.467 9.67 27.467 16 22.33 26.867 16 26.867Zm6.53-8.588c-.356-.178-2.102-1.037-2.427-1.155-.325-.118-.563-.178-.8.178-.237.356-.919 1.155-1.127 1.392-.207.237-.415.267-.77.089-.356-.178-1.503-.554-2.864-1.767-1.059-.944-1.774-2.11-1.982-2.466-.207-.356-.022-.548.156-.726.16-.16.356-.415.533-.622.178-.207.237-.356.356-.593.118-.237.059-.445-.03-.623-.089-.178-.8-1.926-1.096-2.64-.289-.695-.583-.6-.8-.611l-.681-.012c-.237 0-.622.089-.948.445-.326.356-1.244 1.215-1.244 2.963 0 1.748 1.274 3.437 1.452 3.674.178.237 2.507 3.829 6.074 5.371.848.366 1.51.585 2.025.748.851.271 1.625.233 2.237.141.683-.102 2.102-.859 2.398-1.688.296-.829.296-1.54.207-1.688-.089-.148-.326-.237-.681-.415Z" />
            </svg>
        @break

        @case('chart-bar')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        @break

        @case('share')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
        @break

        @case('user-add')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
        @break

        @case('megaphone')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
        @break

        @case('flag')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
            </svg>
        @break

        @case('cog')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        @break

        @case('map')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
        @break

        @default
            <span class="w-5 h-5 inline-block"></span>
    @endswitch
