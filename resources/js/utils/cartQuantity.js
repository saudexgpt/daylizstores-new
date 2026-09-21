/**
 * The rules for how many of one cart line a customer may hold. Pure functions (no Vue, no store, no network)
 * so the cap on quantities has exactly one definition, used by both the store and the cart page, and is unit-tested.
 *
 * "available" is what the server last reported for the line's stock row (stocked − reserved − sold). It is
 * a convenience for the screen only: the order is checked again, under a lock, when it is placed.
 */

/** A stock figure from the server as a whole number >= 0, or null when it isn't known (yet). */
export function knownAvailability(available) {
  if (available === null || available === undefined || available === '') {
    return null;
  }
  const n = Number(available);

  return Number.isFinite(n) ? Math.max(0, Math.floor(n)) : null;
}

/**
 * The most a line can be raised to. While availability is unknown (the check hasn't answered, or the network is
 * down) it can't be raised at all — never above what is in the cart already — so a slow check can't let anyone
 * over-order; lowering is always possible.
 */
export function maxQuantity(available, current) {
  const known = knownAvailability(available);
  if (known !== null) {
    return known;
  }
  const n = Math.floor(Number(current));

  return Number.isFinite(n) && n > 0 ? n : 1;
}

/**
 * Turns whatever was asked for (a click, a typed value, a pasted string) into a quantity that may go in the
 * cart: a whole number from 1 to what is available. Returns null when nothing can be bought (none available),
 * meaning the line should be left alone or removed.
 */
export function clampQuantity(requested, available, current = 1) {
  const max = maxQuantity(available, current);
  if (max < 1) {
    return null;
  }
  const n = Math.floor(Number(requested));
  if (!Number.isFinite(n)) {
    return Math.min(Math.max(Math.floor(Number(current)) || 1, 1), max);
  }

  return Math.min(Math.max(n, 1), max);
}
