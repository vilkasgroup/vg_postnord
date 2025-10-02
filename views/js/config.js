$(document).ready(() => {
  const countryValidCombinations = validCombinations;

  /**
   * Add checkbox in place of hiddenInput field with value from countryValidCombinations
   * filtered with serviceCodeField
   * @param {HTMLInputElement} hiddenInput
   * @param {HTMLSelectElement} serviceCodeField
   * @param {Object} countryValidCombinations
   */
  function addCheckbox(
      hiddenInput,
      serviceCodeField,
      countryValidCombinations
  ) {
      const [code, country] = $(serviceCodeField).val().split('_');

      // filter additional service codes by service code and country
      const filteredCombinations = countryValidCombinations.filter(
          (combination) => combination.serviceCode === code
              && (combination.allowedConsigneeCountries.includes(country) || combination.allowedConsigneeCountries.includes('ALL'))
      );

      let checkboxesContent = '';
      // Sort then loop through the filteredCombination to create checkboxes
      filteredCombinations.forEach(function (combination) {
          checkboxesContent += `
          <div class="checkbox">
            <label for='${hiddenInput.id}_${combination.adnlServiceCode}'>
            <input type="checkbox" name="checkbox" ${hiddenInput.value.includes(combination.adnlServiceCode) ? 'checked' : ''}
                   id="${hiddenInput.id}_${combination.adnlServiceCode}"
                   value="${combination.adnlServiceCode}">
              ${combination.adnlServiceCode} - ${combination.adnlServiceName}
            </label>
          </div>
        `;
      });

      // Add checkboxes
      $(hiddenInput).parent().find('.checkboxes').html(checkboxesContent);
  }

  // use checkbox to fill in hidden text field
  $('.additional_service_codes').each(function () {
    const hiddenInput = this;

    // Find service_code_consigneecountry to populate correct checkboxes
    const serviceCodeField = $(
      `#${hiddenInput.id.replace(
        'additional_service_codes',
        'service_code_consigneecountry'
      )}`
    );

    $(hiddenInput).parent().prepend('<div class="checkboxes"></div>');
    addCheckbox(hiddenInput, serviceCodeField, countryValidCombinations);
  });

  // update the hidden input field when checkboxes are clicked
  $(document.body).on('change', '.checkboxes input[type="checkbox"]', function () {
    const checkboxesContainer = $(this).closest('.checkboxes');
    const hiddenInput = checkboxesContainer.siblings('.additional_service_codes')[0];

    const inputValue = [];
    checkboxesContainer.find('input[name="checkbox"]').each(function () {
      if (this.checked) {
        inputValue.push(this.value);
      }
    });
    hiddenInput.value = JSON.stringify(inputValue);
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

    addCheckbox(hiddenInput, serviceCodeField, countryValidCombinations);
  })
});
