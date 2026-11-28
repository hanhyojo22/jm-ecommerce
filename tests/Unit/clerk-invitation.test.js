import assert from 'node:assert/strict';
import { test } from 'node:test';
import { signedInEmailForInvitation } from '../../resources/js/clerk-invitation.js';

const owner = { user: { primaryEmailAddress: { emailAddress: 'owner@example.com' } } };

test('an invitation opened while signed in to Clerk names that account', () => {
    assert.equal(signedInEmailForInvitation(owner, '?__clerk_ticket=abc&__clerk_status=sign_up'), 'owner@example.com');
});

test('the sign-up form shows when nobody is signed in or there is no invitation', () => {
    assert.equal(signedInEmailForInvitation({ user: null }, '?__clerk_ticket=abc'), null);
    assert.equal(signedInEmailForInvitation(owner, ''), null);
    assert.equal(signedInEmailForInvitation(undefined, '?__clerk_ticket=abc'), null);
});

test('a signed-in user without a primary email is still stopped', () => {
    assert.equal(signedInEmailForInvitation({ user: {} }, '?__clerk_ticket=abc'), 'another account');
});
