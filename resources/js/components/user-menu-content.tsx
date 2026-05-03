import { Link, router } from '@inertiajs/react';
import { LogOut, Settings, LayoutDashboard } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import type { User } from '@/types';

type Props = {
    user: User;
    isAdmin?: boolean;
};

export function UserMenuContent({ user, isAdmin = false }: Props) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const logoutHref = isAdmin ? route('admin.logout') : route('logout');

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {isAdmin && (
                    <DropdownMenuItem asChild>
                        <a className="flex w-full cursor-pointer items-center" href="/admin">
                            <LayoutDashboard className="mr-2 h-4 w-4" />
                            CMS Dashboard
                        </a>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link className="flex w-full cursor-pointer items-center" href={route('profile.edit')} prefetch onClick={cleanup}>
                        <Settings className="mr-2 h-4 w-4" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="flex w-full cursor-pointer items-center"
                    href={logoutHref}
                    method="post"
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2 h-4 w-4" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
