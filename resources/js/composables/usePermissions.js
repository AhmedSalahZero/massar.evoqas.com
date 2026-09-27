// ══════════════════════════════════════════════════════════════════
//  Massar — usePermissions
//  Location: resources/js/composables/usePermissions.js
//
//  The frontend half of the permission backbone. The server shares
//  the keys the signed-in user holds (auth.user.permissions — see
//  config/permissions.php and HandleInertiaRequests):
//
//      const { can, isSuperAdmin } = usePermissions();
//      v-if="can('team.manage')"
//
//  This only HIDES what someone cannot use. The real check is always
//  on the server (route middleware `can:` / FormRequest authorize),
//  so hiding a button is a convenience, never the protection.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const keys = computed(() => new Set(user.value?.permissions ?? []));

    const can = (key) => keys.value.has(key);
    const canAny = (...list) => list.some((k) => keys.value.has(k));

    const isSuperAdmin = computed(() => user.value?.role === 'super_admin');
    const isCompanyAdmin = computed(() => user.value?.role === 'company_admin');

    return { user, can, canAny, isSuperAdmin, isCompanyAdmin };
}
