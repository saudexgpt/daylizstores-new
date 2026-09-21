/**
 * The name shown beside a colour swatch on the product page. Colours are stored the way they are entered:
 * CSS names run together ("DarkRed", "PeachPuff"), sometimes in capitals ("CREAM"), sometimes two colours
 * ("Black/White"), and some stock has no colour at all.
 */
const NONE = 'Standard';

function words(part) {
  const text = part.trim().replace(/\s+/g, ' ');
  // CAPITALS become "Cream"; joined-up CSS names get their spaces back: "DarkRed" -> "Dark Red"
  const spaced = /^[A-Z]+$/.test(text) ? text.toLowerCase() : text.replace(/([a-z])([A-Z])/g, '$1 $2');

  return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

export function colorLabel(color) {
  if (color === null || color === undefined || String(color).trim() === '') {
    return NONE;
  }
  const parts = String(color).split('/').map(words).filter(Boolean);

  return parts.length ? parts.join(' / ') : NONE;
}

/** True when the product has at least one real colour, so a colour picker is worth showing. */
export function hasNamedColors(colors) {
  return (colors || []).some(color => color !== null && color !== undefined && String(color).trim() !== '');
}
