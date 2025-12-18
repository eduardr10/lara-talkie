<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Username -->
            <flux:input
                name="name"
                :label="__('Username')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="username"
                :placeholder="__('Choose a username')"
            />

            <!-- Pin -->
            <flux:input
                name="pin"
                :label="__('6-digit Pin')"
                type="password"
                required
                pattern="[0-9]{6}"
                maxlength="6"
                minlength="6"
                autocomplete="off"
                :placeholder="__('Enter a 6-digit pin')"
            />
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts.auth>
