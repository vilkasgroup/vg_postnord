$(document).ready(() => {
  const validCombinations = additionalServiceCodes;

  /**
   * Filter combinations based on service code and country.
   * @param {JQuery<HTMLSelectElement>} serviceCodeField
   */
  function filterCombinations(serviceCodeField) {
    const [code, country] = $(serviceCodeField).val().split('_');
    return validCombinations.filter(
        (combination) => combination.serviceCode === code
            && (combination.allowedConsigneeCountries.includes(country) || combination.allowedConsigneeCountries.includes('ALL'))
    );
  }

  /**
   * Render additional service code checkboxes for a given hidden input and service code field.
   * @param {HTMLInputElement} hiddenInput
   * @param {JQuery<HTMLSelectElement>} serviceCodeField
   */
  function renderCheckboxes(hiddenInput, serviceCodeField) {
    const combinations = filterCombinations(serviceCodeField);
    const html = combinations.map(combination => renderCheckbox(hiddenInput, combination)).join('');
    $(hiddenInput).siblings('.checkboxes').html(html);
  }

  /**
   * Render an additional service code checkbox.
   * @param {HTMLInputElement} hiddenInput
   * @param {object} combination
   * @returns {string}
   */
  function renderCheckbox(hiddenInput, combination) {
    const id = `${hiddenInput.id}_${combination.adnlServiceCode}`;
    const checked = hiddenInput.value.includes(combination.adnlServiceCode)
    return `
      <div class="checkbox">
        <label for='${id}'>
        <input type="checkbox"
               name="checkbox"
               ${checked ? 'checked' : ''}
               id="${id}"
               value="${combination.adnlServiceCode}">
          ${combination.adnlServiceCode} - ${combination.adnlServiceName}
        </label>
      </div>
    `;
  }

  /**
   * Update the hidden input field with the selected additional service codes.
   * @param {jQuery} checkboxesContainer
   */
  function updateHiddenInput(checkboxesContainer) {
    const hiddenInput = checkboxesContainer.siblings('.additional_service_codes')[0];
    const selectedValues = checkboxesContainer
      .find('input[type="checkbox"]:checked')
      .map(function() {
        return this.value;
      })
      .get();
    hiddenInput.value = JSON.stringify(selectedValues);
  }

  // initial page setup
  $('.additional_service_codes').each(function () {
    const hiddenInput = this;
    // find the corresponding service code field
    const serviceCodeField = $(
      `#${hiddenInput.id.replace(
        'additional_service_codes',
        'service_code_consigneecountry'
      )}`
    );
    $(hiddenInput).parent().prepend('<div class="checkboxes"></div>');
    renderCheckboxes(hiddenInput, serviceCodeField);
  });

  // update the hidden input field when checkboxes are clicked
  $(document.body).on('change', '.checkboxes input[type="checkbox"]', function () {
    const checkboxesContainer = $(this).closest('.checkboxes');
    updateHiddenInput(checkboxesContainer);
  })

  // update checkboxes when carrier service code is changed
  $(document.body).on('change', 'select[id$="_service_code_consigneecountry"]', function () {
    const serviceCodeField = this;
    // find the corresponding hidden input field
    const hiddenInputId = '#' + serviceCodeField.id.replace(
        '_service_code_consigneecountry',
        '_additional_service_codes'
    );
    const hiddenInput = $(hiddenInputId)[0];
    renderCheckboxes(hiddenInput, serviceCodeField);
  })
});
