/**
 * The email of the Clerk user this browser is still signed in as, when an invitation link is opened.
 * Clerk's sign-up form would just continue as that person (often the owner who sent the invitation),
 * so the page asks them to sign out instead. Null when there is no invitation or nobody is signed in.
 */
export function signedInEmailForInvitation(clerk, search) {
    if (!new URLSearchParams(search).has('__clerk_ticket') || !clerk?.user) {
        return null;
    }

    return clerk.user.primaryEmailAddress?.emailAddress ?? 'another account';
}

/**
 * Replaces the sign-up form with a sign-out prompt that comes back to the same invitation link.
 */
export function renderInvitationSignOut(element, email, signOut) {
    const heading = document.createElement('h2');
    heading.className = 'text-lg font-semibold text-ink';
    heading.textContent = 'Sign out to accept this invitation';

    const message = document.createElement('p');
    message.className = 'mt-2 text-sm text-muted';
    message.append('This browser is signed in as ');
    const who = document.createElement('strong');
    who.className = 'text-ink';
    who.textContent = email;
    message.append(who, '. The invitation is for a new team member, so sign out first, then create their login with the invited email.');

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'mt-5 min-h-11 w-full rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:opacity-70';
    button.textContent = 'Sign out and accept the invitation';
    button.addEventListener('click', () => {
        button.disabled = true;
        button.textContent = 'Signing out…';
        signOut();
    });

    element.replaceChildren(heading, message, button);
}
