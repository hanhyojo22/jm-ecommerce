/**
 * Clerk's hosted screens (sign-in, sign-up, user button, account settings) painted with the
 * JMMart palette, so an account screen reads as part of the marketplace rather than beside it.
 *
 * The palette itself is not repeated here: it is read from the tokens on :root, which app.css
 * defines, so a colour that changes there changes on these screens too. Clerk derives its own
 * shades from these values and so needs real colours, not var() references.
 *
 * Element keys are Clerk's appearance API (see @clerk/ui elementDescriptors); keys without a
 * descriptor — the navbar heading, for instance — are tuned in app.css under
 * [data-clerk-component='user-profile'].
 */
const token = (name, fallbackAtTimeOfWriting) => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value || fallbackAtTimeOfWriting;
};

const ink = token('--color-fg', '#182c48');
const muted = token('--color-muted', '#596b83');
const line = token('--color-border', '#e3e8f1');
const brand = token('--color-accent', '#205bf4');
const brandHover = token('--color-accent-hover', '#1d4ed8');
const surface = token('--color-surface', '#ffffff');

export const clerkAppearanceVariables = {
    colorPrimary: brand,
    colorPrimaryForeground: '#ffffff',
    colorForeground: ink,
    colorMutedForeground: muted,
    colorBackground: surface,
    colorInput: surface,
    colorInputForeground: ink,
    colorBorder: line,
    // Clerk shades its neutral scale from this; it is Clerk's own grey, not one of our tokens.
    colorNeutral: '#64748b',
    colorRing: brand,
    colorDanger: token('--color-danger', '#dc2626'),
    colorSuccess: token('--color-success', '#166534'),
    colorMuted: token('--color-bg', '#f6f8fc'),
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    fontSize: '0.875rem',
    borderRadius: '10px',
};

const flushCardElements = {
    rootBox: { width: '100%', maxWidth: '100%', minWidth: '0' },
    cardBox: { width: '100%', maxWidth: '100%', minWidth: '0' },
    card: { width: '100%', maxWidth: '100%', minWidth: '0' },
};

export const clerkComponentAppearance = {
    variables: clerkAppearanceVariables,
    options: { elevation: 'flush' },
    elements: flushCardElements,
};

/**
 * Account settings mounts Clerk's UserProfile inside one of our own cards (see
 * x-marketplace.account-profile). Clerk still draws a 55rem card at a fixed 44rem height with
 * its own chrome, so the surface is stripped back here and left to follow the content; the
 * navbar and rows take the same quiet styling as the rest of the account pages.
 */
export const clerkUserProfileAppearance = {
    variables: clerkAppearanceVariables,
    options: { elevation: 'flush' },
    elements: {
        ...flushCardElements,
        cardBox: {
            width: '100%',
            maxWidth: '100%',
            minWidth: '0',
            height: 'auto',
            flexWrap: 'wrap',
            borderWidth: '0',
            boxShadow: 'none',
            borderRadius: '0',
            overflow: 'visible',
        },
        card: {
            width: '100%',
            maxWidth: '100%',
            minWidth: '0',
            backgroundColor: 'transparent',
            borderWidth: '0',
            borderRadius: '0',
            padding: '0',
            margin: '0',
        },
        navbar: {
            flex: '0 0 14rem',
            width: '14rem',
            maxWidth: '14rem',
            marginInlineEnd: '0',
            padding: '1rem 0.75rem',
            background: '#f8fafc',
            borderInlineEnd: '1px solid #e9eef6',
            gap: '0.75rem',
            // Clerk's flush cards hide the decorative dev overlay for the footer notice; do the same.
            '& [data-clerk-dev-mode-overlay]': { display: 'none' },
        },
        navbarButtons: { gap: '0.25rem', width: '100%' },
        navbarButton: {
            minHeight: '2.75rem',
            padding: '0.5rem 0.75rem',
            gap: '0.5rem',
            borderRadius: '10px',
            fontSize: '0.875rem',
            fontWeight: '500',
            backgroundColor: 'transparent',
            transitionProperty: 'background-color, color',
            transitionDuration: '150ms',
            '&[data-active]': {
                backgroundColor: 'rgba(32, 91, 244, 0.09)',
                color: brandHover,
                fontWeight: '600',
            },
        },
        navbarButtonIcon: { width: '1.125rem', height: '1.125rem' },
        // Below 768px Clerk trades the navbar for this menu row.
        navbarMobileMenuRow: {
            backgroundColor: '#f8fafc',
            borderBottom: '1px solid #e9eef6',
            padding: '0.5rem 0.75rem',
            marginBottom: '0',
        },
        navbarMobileMenuButton: {
            minHeight: '2.75rem',
            color: ink,
            fontSize: '0.875rem',
            fontWeight: '600',
            gap: '0.5rem',
            borderRadius: '10px',
        },
        scrollBox: {
            backgroundColor: '#ffffff',
            borderWidth: '0',
            borderRadius: '0',
            marginBlock: '0',
            marginInlineEnd: '0',
        },
        pageScrollBox: { height: 'auto', overflowY: 'visible' },
        profilePageContent: {
            padding: '1rem 1.5rem 1.5rem',
            // Our card loses the workspace rail before Clerk's own 992px split; keep the gutters
            // honest while the card is between the two widths.
            '@media (max-width: 1180px)': { padding: '1rem' },
        },
        profilePage: { gap: '1rem' },
        headerTitle: {
            fontSize: '1.125rem',
            fontWeight: '600',
            letterSpacing: '-0.01em',
            color: ink,
            marginBottom: '0.5rem',
        },
        headerSubtitle: { fontSize: '0.875rem', color: muted },
        headerBackLink: { fontSize: '0.875rem', fontWeight: '500', color: muted },
        profileSection: { borderTopColor: '#edf1f7', paddingBlock: '1rem', gap: '1rem' },
        // Clerk centres the label against its row; settings rows read better top-aligned.
        profileSectionHeader: {
            width: '11.5rem',
            flexShrink: '0',
            gap: '0.25rem',
            alignSelf: 'flex-start',
            marginTop: '0',
            transform: 'none',
            '@media (max-width: 1180px)': { width: '9.5rem' },
        },
        profileSectionTitleText: { fontSize: '0.875rem', fontWeight: '600', color: ink },
        profileSectionSubtitle: { fontSize: '0.8125rem' },
        profileSectionItem: { padding: '0.25rem 0' },
        profileSectionPrimaryButton: {
            minHeight: '2.75rem',
            color: brand,
            fontSize: '0.875rem',
            fontWeight: '600',
            whiteSpace: 'nowrap',
            flexShrink: '0',
            borderRadius: '10px',
            padding: '0.5rem 0.75rem',
            '&:hover': { backgroundColor: 'rgba(32, 91, 244, 0.06)' },
        },
        formFieldInput: {
            minHeight: '2.75rem',
            paddingInline: '0.75rem',
            borderRadius: '10px',
            borderWidth: '1px',
            borderStyle: 'solid',
            borderColor: line,
            backgroundColor: '#ffffff',
            boxShadow: 'none',
            fontSize: '0.875rem',
        },
        formFieldLabel: { fontSize: '0.875rem', fontWeight: '600', color: muted },
        formFieldHintText: { fontSize: '0.8125rem' },
        formFieldErrorText: { fontSize: '0.8125rem', fontWeight: '500' },
        formFieldInputShowPasswordButton: { color: muted, borderRadius: '8px' },
        formButtonPrimary: {
            minHeight: '2.75rem',
            paddingInline: '1.125rem',
            borderRadius: '10px',
            fontSize: '0.875rem',
            fontWeight: '600',
            backgroundColor: brand,
            color: '#ffffff',
            boxShadow: 'none',
            transitionProperty: 'background-color',
            transitionDuration: '150ms',
            '&:hover': { backgroundColor: brandHover },
            '&:focus': { boxShadow: 'none' },
        },
        formButtonReset: {
            minHeight: '2.75rem',
            paddingInline: '1rem',
            borderRadius: '10px',
            fontSize: '0.875rem',
            fontWeight: '600',
            color: muted,
            backgroundColor: 'transparent',
            boxShadow: 'none',
            '&:hover': { backgroundColor: '#eef2f8', color: ink },
        },
        badge: {
            borderRadius: '999px',
            paddingBlock: '0.0625rem',
            paddingInline: '0.5rem',
            fontSize: '0.6875rem',
            fontWeight: '600',
        },
        avatarBox: { width: '3rem', height: '3rem', borderRadius: '999px', overflow: 'hidden' },
        avatarImage: { borderRadius: '999px' },
        menuButton: {
            width: '2.75rem',
            height: '2.75rem',
            minHeight: '2.75rem',
            borderRadius: '8px',
            color: muted,
            boxShadow: 'none',
            '&:hover': { backgroundColor: '#eef2f8', color: ink },
        },
        menuButtonEllipsis: { width: '1rem', height: '1rem' },
        menuList: {
            padding: '0.25rem',
            borderRadius: '12px',
            borderWidth: '1px',
            borderStyle: 'solid',
            borderColor: line,
            boxShadow: '0 16px 32px -16px rgba(24, 44, 72, 0.3)',
        },
        menuItem: { borderRadius: '8px', padding: '0.5rem 0.625rem', fontSize: '0.875rem' },
        modalContent: { borderRadius: '14px', boxShadow: '0 24px 64px -24px rgba(24, 44, 72, 0.45)' },
        modalCloseButton: { color: muted, borderRadius: '10px' },
        alert: { borderRadius: '10px', fontSize: '0.875rem' },
        dividerLine: { backgroundColor: line },
        // The footer keeps Clerk's notice but on our card's own surface, across the bottom.
        footer: {
            marginTop: '0',
            paddingTop: '0',
            background: '#fbfcfe',
            borderTopWidth: '1px',
            borderTopStyle: 'solid',
            borderTopColor: '#edf1f7',
            '&:empty': { display: 'none' },
            '@media (min-width: 769px)': { flexBasis: '100%' },
        },
    },
};
