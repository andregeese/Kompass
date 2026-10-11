<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
?>

<div x-data="loginComponent()" class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8 w-full max-w-sm mx-auto">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="mt-10 text-center text-2xl font-bold leading-9 tracking-tight text-slate-900 dark:text-white">
            <span x-text="isForgotPassword ? 'Passwort zurücksetzen' : (isRegister ? 'Neuen Account registrieren' : 'In den CRM Hub einloggen')"></span>
        </h2>
    </div>
    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-xl">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-white">E-Mail-Adresse</label>
                <input type="email" x-model="formData.email" required class="mt-2 block w-full rounded-xl border-0 py-2.5 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 bg-transparent dark:text-white focus:ring-2 focus:ring-blue-600 sm:text-sm">
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-white" x-text="isForgotPassword ? 'Neues Passwort' : 'Passwort'"></label>
                    <button type="button" @click="switchToForgotPassword()" x-show="!isRegister && !isForgotPassword" class="text-xs text-blue-600 hover:text-blue-500 font-semibold bg-transparent border-none cursor-pointer">
                        Passwort vergessen?
                    </button>
                </div>
                <input type="password" x-model="formData.password" required class="mt-2 block w-full rounded-xl border-0 py-2.5 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 bg-transparent dark:text-white focus:ring-2 focus:ring-blue-600 sm:text-sm">
            </div>

            <!-- Admin Key Feld (Nur sichtbar bei Passwort vergessen) -->
            <div x-show="isForgotPassword" x-transition>
                <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-white">Admin-Key (APP_KEY)</label>
                <input type="password" x-model="formData.app_key" :required="isForgotPassword" placeholder="Key aus der .env Datei" class="mt-2 block w-full rounded-xl border-0 py-2.5 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 bg-transparent dark:text-white focus:ring-2 focus:ring-blue-600 sm:text-sm">
            </div>

            <button type="submit" :disabled="loading" class="flex w-full justify-center rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 transition-all">
                <span x-text="loading ? 'Bitte warten...' : (isForgotPassword ? 'Passwort neu setzen' : (isRegister ? 'Registrieren' : 'Anmelden'))"></span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            <!-- Navigation für Register / Login -->
            <button x-show="!isForgotPassword" @click="isRegister = !isRegister" class="font-semibold leading-6 text-blue-600 hover:text-blue-500 bg-transparent border-none cursor-pointer">
                <span x-text="isRegister ? 'Bereits ein Konto? Hier anmelden' : 'Noch kein Konto? Jetzt registrieren'"></span>
            </button>

            <!-- Abbrechen-Button für Passwort vergessen -->
            <button x-show="isForgotPassword" @click="switchToLogin()" class="font-semibold leading-6 text-blue-600 hover:text-blue-500 bg-transparent border-none cursor-pointer">
                Zurück zum Login
            </button>
        </p>
    </div>
</div>