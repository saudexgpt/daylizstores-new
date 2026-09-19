export default function showItemsInCartons(quantity, quantity_per_carton, unit = 'unit'){
  const whole_num = Math.floor(quantity / quantity_per_carton);
  const display_whole_num = (whole_num > 0) ? '<strong>' + whole_num + 'CTN</strong>' : '';
  const modulus = quantity % quantity_per_carton;
  const display_modulus = (modulus > 0) ? '&nbsp;<strong>' + modulus + unit + '</strong>' : '';
  if (display_whole_num === '' && display_modulus === '') {
    return '';
  }
  return display_whole_num + ' ' + display_modulus;
}
