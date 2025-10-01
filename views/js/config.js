$(document).ready(() => {
  const countryValidCombinations = validCombinations;

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
      const [code, country] = serviceCodeField.val().split('_');

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

      // Add checkboxes then click to update/clear hiddenInputField
      $(hiddenInput).parent().find('.checkboxes').html(checkboxesContent);
      $(hiddenInput).parent().find('.checkboxes').click();
    }

    $(hiddenInput).parent().prepend('<div class="checkboxes"></div>');
    addCheckbox(hiddenInput, serviceCodeField, countryValidCombinations);

    // Loop through checkboxes on click and add value to the hiddenInput
    $(this)
      .parent()
      .click(function () {
        const inputValue = [];
        $(this)
          .find('input[name="checkbox"]')
          .each(function () {
            if (this.checked) {
              inputValue.push(this.value);
            }
          });
        hiddenInput.value = JSON.stringify(inputValue);
      });

    // Update checkbox after service code changed
    $(serviceCodeField).change(() => {
      addCheckbox(hiddenInput, serviceCodeField, countryValidCombinations);
    });
  });
});
