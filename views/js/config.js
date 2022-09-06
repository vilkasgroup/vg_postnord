$(document).ready(function () {
    const issuerCountry = $('#VG_POSTNORD_ISSUER_COUNTRY').val()
    const countryValidCombinations = validCombinations
        .reduce(function (previousValue, currentValue) {
            if (currentValue.issuerCountryCode === issuerCountry) {
                return currentValue.adnlServiceCodeCombDetails
            }
            return previousValue
        }, [])

    // use checkbox to fill in hidden text field
    $('.additional_service_codes').each(function (index) {
        const hiddenInput = this
        const serviceCodeField = $('#' + this.id.replace('additional_service_codes', 'service_code_consigneecountry'))
        
        $(hiddenInput).parent().prepend(`<div class="checkboxes"></div>`)
        addCheckbox(hiddenInput, serviceCodeField, countryValidCombinations)

        $(this).parent().click(function () {
            const checkboxes = $(this).find('input[name="checkbox"]')
            let inputValue = []
            for (checkbox of checkboxes) {
                if (checkbox.checked)
                    inputValue.push(checkbox.value)
            }
            hiddenInput.value = inputValue.join(',')
        })

        $(serviceCodeField).change(function () {
            addCheckbox(hiddenInput, serviceCodeField, countryValidCombinations)
        })
    })

    function addCheckbox(hiddenInput, serviceCodeField, countryValidCombinations) {
        const [code, country] = serviceCodeField.val().split('_')

        // There are a few duplications with different valid time
        // So use reduce to avoid duplication of adnlServiceCode
        const filteredCombination = countryValidCombinations.sort(function (a, b) {
            if (a.adnlServiceCode > b.adnlServiceCode) {
                return 1
            }
            if (a.adnlServiceCode > b.adnlServiceCode) {
                return -1
            }
            return 0
        }).reduce(
            function (previousValue, currentValue) {
                if (currentValue.serviceCode === code && currentValue.allowedConsigneeCountry === country) {
                    if (!previousValue.hasOwnProperty(currentValue['adnlServiceCode'])) {
                        previousValue[currentValue['adnlServiceCode']] = currentValue
                    }
                }
                return previousValue
            }, {})

        let checkboxesContent = '';
        for (const element in filteredCombination) {
            checkboxesContent += (`<div class="checkbox">
                <label for="${hiddenInput.id}_${filteredCombination[element].adnlServiceCode}">
                <input type="checkbox" name="checkbox" ${hiddenInput.value.includes(element)?'checked':''}
                id="${hiddenInput.id}_${filteredCombination[element].adnlServiceCode}" value="${filteredCombination[element].adnlServiceCode}">
                ${filteredCombination[element].adnlServiceName}
            </label>
        </div>`)
        }

        $(hiddenInput).parent().find(".checkboxes").html(checkboxesContent)
        // Clear hiddenInputField after checkboxes update
        $(hiddenInput).parent().find(".checkboxes").click()
    }
})