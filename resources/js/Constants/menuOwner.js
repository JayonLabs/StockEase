import {
    LayoutDashboard,
    Building2,
    CreditCard,
    Package,
    ScrollText,
    UserCircle,
} from 'lucide-vue-next';

export const ownerMenuSections = [
    {
        label: 'Overview',
        collapsible: false,
        items: [
            {
                title: 'Dashboard',
                routeName: 'platform.owner.dashboard',
                icon: LayoutDashboard,
                roles: ['platform_owner'],
            },
        ],
    },
    {
        label: 'Management',
        collapsible: true,
        items: [
            {
                title: 'Companies',
                routeName: 'platform.owner.companies.index',
                activeRoute: 'platform.owner.companies.*',
                icon: Building2,
                roles: ['platform_owner'],
            },
            {
                title: 'Subscriptions',
                routeName: 'platform.owner.subscriptions.index',
                activeRoute: 'platform.owner.subscriptions.*',
                icon: CreditCard,
                roles: ['platform_owner'],
            },
            {
                title: 'Plans',
                routeName: 'platform.owner.plans.index',
                activeRoute: 'platform.owner.plans.*',
                icon: Package,
                roles: ['platform_owner'],
            },
        ],
    },
    {
        label: 'System',
        collapsible: true,
        items: [
            {
                title: 'Queue Worker Logs',
                routeName: 'platform.owner.queue-worker-logs.index',
                activeRoute: 'platform.owner.queue-worker-logs.*',
                icon: ScrollText,
                roles: ['platform_owner'],
            },
        ],
    },
    {
        label: 'Account',
        collapsible: false,
        items: [
            {
                title: 'Profile',
                routeName: 'platform.owner.profile.edit',
                activeRoute: 'platform.owner.profile.*',
                icon: UserCircle,
                roles: ['platform_owner'],
            },
        ],
    },
];
