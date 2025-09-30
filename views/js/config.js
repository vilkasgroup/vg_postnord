/* eslint-disable prefer-arrow-callback */
/* eslint-disable comma-dangle */
/* eslint-disable no-param-reassign */
/* eslint-disable func-names */
/* eslint-disable no-undef */
/* eslint-disable no-shadow */
$(document).ready(() => {
  // Get issuer country, onChange is not necessary since reload is required
  const issuerCountry = $('#VG_POSTNORD_ISSUER_COUNTRY').val();

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

      // There are a few duplications with different valid time
      // So use reduce to avoid duplication of adnlServiceCode
      const filteredCombination = countryValidCombinations.reduce(
        (previousValue, currentValue) => {
          if (
            currentValue.serviceCode === code
            && currentValue.allowedConsigneeCountry === country
          ) {
            previousValue[currentValue.adnlServiceCode] = currentValue;
          }
          return previousValue;
        },
        {}
      );

      let checkboxesContent = '';
      // Sort then loop through the filteredCombination to create checkboxes
      Object.keys(filteredCombination)
        .sort()
        .forEach(function (element) {
          checkboxesContent += `
            <div class="checkbox">
              <label for='${hiddenInput.id}_${filteredCombination[element].adnlServiceCode}'>
              <input type="checkbox" name="checkbox" ${hiddenInput.value.includes(element) ? 'checked' : ''}
                     id="${hiddenInput.id}_${filteredCombination[element].adnlServiceCode}"
                     value="${filteredCombination[element].adnlServiceCode}">
                ${filteredCombination[element].adnlServiceCode} - ${filteredCombination[element].adnlServiceName}
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
