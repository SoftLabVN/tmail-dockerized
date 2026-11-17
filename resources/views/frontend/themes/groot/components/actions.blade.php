<div x-data="{ in_app: {{ $in_app ? 'true' : 'false' }} }" class="p-3 md:p-0 w-full md:w-2/4 order-2">
    <div x-show.transition.in="!in_app" class="space-y-5" style="display: none">
        <div class="border-2 border-white border-opacity-10 border-dashed rounded-md flex flex-col">
            <div class="text-lg font-bold text-center mt-8 mb-2">{{ __('Your Temporary Email Address') }}
            </div>
            <div class="m-5 flex">
                <div class="relative flex-1">
                    <x-jet-dropdown align="top" width="w-full">
                        <x-slot name="trigger">
                            <div class="block appearance-none w-full bg-white text-white py-4 px-5 pr-8 bg-opacity-10 rounded-md cursor-pointer focus:outline-none select-none"
                                id="email_id">{{ $email }}</div>
                        </x-slot>
                        <x-slot name="content">
                            @foreach ($emails as $email)
                                <x-jet-dropdown-link href="{{ route('switch', $email) }}">
                                    {{ $email }}
                                </x-jet-dropdown-link>
                            @endforeach
                        </x-slot>
                    </x-jet-dropdown>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-white">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                        </svg>
                    </div>
                </div>
                <div id="btn_copy"
                    class="flex items-center bg-indigo-600 rounded-md text-white text-center py-4 px-5 ml-3 hover:bg-opacity-75 cursor-pointer"
                    style="background-color: {{ config('app.settings.colors.secondary') }}">
                    <i class="far fa-copy"></i>
                </div>
            </div>
        </div>
        <div class="actions grid grid-cols-3 gap-3">
            <div onclick="document.getElementById('refresh').classList.remove('pause-spinner')"
                class="flex justify-center items-center bg-white bg-opacity-10 hover:bg-opacity-25 text-white rounded-md text-center cursor-pointer py-3 px-4">
                <i id="refresh" class="fas fa-sync-alt fa-spin"></i>
                <div class="text-sm ml-3">{{ __('Refresh') }}</div>
            </div>
            <div x-on:click="in_app = true"
                class="flex justify-center items-center bg-white bg-opacity-10 hover:bg-opacity-25 text-white rounded-md text-center cursor-pointer py-3 px-4">
                <i class="far fa-plus-square"></i>
                <div class="text-sm ml-3">{{ __('New') }}</div>
            </div>
            <div wire:click="deleteEmail"
                class="flex justify-center items-center bg-white bg-opacity-10 hover:bg-opacity-25 text-white rounded-md text-center cursor-pointer py-3 px-4">
                <i class="far fa-trash-alt"></i>
                <div class="text-sm ml-3">{{ __('Delete') }}</div>
            </div>
        </div>
    </div>
    <div x-show.transition.in="in_app" class="space-y-5" style="display: none">
        <div class="border-2 border-white border-opacity-10 border-dashed rounded">
            <div class="m-5 flex space-x-3">
                <form wire:submit.prevent="create" method="post" class="flex-1 space-x-3 flex">
                    <div class="flex-1 space-y-3">
                        <input
                            class="block appearance-none w-full rounded-md py-4 px-5 bg-white text-white bg-opacity-10 focus:outline-none placeholder-white placeholder-opacity-50"
                            type="text" name="user" id="user" wire:model="user" placeholder="{{ __('Enter Username') }}">
                        <div class="relative">
                            <x-jet-dropdown align="top" width="w-full">
                                <x-slot name="trigger">
                                    <input x-ref="domain" type="text"
                                        class="block appearance-none w-full bg-white text-white py-4 px-5 pr-8 bg-opacity-10 rounded-md cursor-pointer focus:outline-none select-none placeholder-white placeholder-opacity-50"
                                        placeholder="{{ __('Select Domain') }}" name="domain" id="domain"
                                        wire:model="domain" readonly>
                                </x-slot>
                                <x-slot name="content">
                                    @foreach ($domains as $domain)
                                        <a x-on:click="$refs.domain.value = '{{ $domain }}'; $wire.setDomain('{{ $domain }}')"
                                            class='block px-4 py-2 text-sm leading-5 text-gray-700 cursor-pointer hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out'>{{ $domain }}</a>
                                    @endforeach
                                </x-slot>
                            </x-jet-dropdown>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-white">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path
                                        d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <button id="create"
                        class="flex items-center bg-indigo-600 rounded-md text-white text-center py-4 px-5 hover:bg-opacity-75 cursor-pointer"
                        style="background-color: {{ config('app.settings.colors.secondary') }}">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </form>
                <div class="flex items-center">
                    <div class="border border-dashed border-white h-6"></div>
                </div>
                <form wire:submit.prevent="random" method="post" class="flex">
                    <button id="random"
                        class="flex items-center bg-green-400 rounded-md text-white text-center py-4 px-5 hover:bg-opacity-75 cursor-pointer"
                        style="background-color: {{ config('app.settings.colors.tertiary') }}">
                        <i class="fas fa-random"></i>
                    </button>
                </form>
            </div>
        </div>
        @if (count($emails) > 0 && $in_app)
            <div>
                <a href="{{ route('app') }}"
                    class="flex items-center bg-white bg-opacity-25 rounded-md text-white text-center py-3 px-5 space-x-3 hover:bg-opacity-10 cursor-pointer text-sm">
                    <i class="fas fa-chevron-left"></i>
                    <span>{{ __('Get back to MailBox') }}</span>
                </a>
            </div>
        @endif
        @if (!$in_app)
            <div>
                <div x-on:click="in_app = false"
                    class="flex justify-center items-center bg-white bg-opacity-25 rounded-md text-white text-center py-3 px-5 hover:bg-opacity-10 cursor-pointer text-sm">
                    <span>{{ __('Cancel') }}</span>
                </div>
            </div>
        @endif
    </div>
    @if(config('app.settings.recaptcha.enabled', false))
    <div wire:loading>
        <div class="absolute top-0 left-0 w-full h-full bg-black bg-opacity-75 text-white flex justify-center items-center z-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </div>
    </div>
    <script src="https://www.google.com/recaptcha/api.js?render={{ config('app.settings.recaptcha.site_key') }}"></script>
    <script>
        const handle = (e) => {
            e.preventDefault();
            grecaptcha.ready(function() {
                grecaptcha.execute('{{ config('app.settings.recaptcha.site_key') }}', { action: 'submit' }).then(function(token) {
                    Livewire.emit('checkCaptcha', token, e.target.id);
                });
            });
        }
        document.getElementById('create').addEventListener('click', handle);
        document.getElementById('random').addEventListener('click', handle);
    </script>
    @endif
</div>
