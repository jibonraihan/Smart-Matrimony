document.addEventListener('DOMContentLoaded', function () {
    const division = document.getElementById('division_id');
    const district = document.getElementById('district_id');
    const upazila = document.getElementById('upazila_id');
    if (!division || !district || !upazila) return;

    const ajaxBase = '../ajax/';
    const selectedDistrict = district.dataset.selected || '';
    const selectedUpazila = upazila.dataset.selected || '';

    function setLoading(select, text) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = text;
        select.appendChild(option);
        select.disabled = true;
    }

    async function loadDistricts(divisionId, preserve) {
        if (!divisionId) {
            district.innerHTML = '<option value="">Any district</option>';
            upazila.innerHTML = '<option value="">Any upazila</option>';
            district.disabled = true;
            upazila.disabled = true;
            return;
        }
        setLoading(district, 'Loading districts...');
        upazila.innerHTML = '<option value="">Any upazila</option>';
        upazila.disabled = true;
        try {
            const response = await fetch(ajaxBase + 'get_districts.php?division_id=' + encodeURIComponent(divisionId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('District request failed');
            district.innerHTML = '<option value="">Any district</option>' + (await response.text()).replace(/^<option value="">Select District<\/option>/, '');
            district.disabled = false;
            if (preserve && selectedDistrict) {
                district.value = selectedDistrict;
            }
            await loadUpazilas(district.value, preserve);
        } catch (error) {
            district.innerHTML = '<option value="">Could not load districts</option>';
            district.disabled = true;
        }
    }

    async function loadUpazilas(districtId, preserve) {
        if (!districtId) {
            upazila.innerHTML = '<option value="">Any upazila</option>';
            upazila.disabled = true;
            return;
        }
        setLoading(upazila, 'Loading upazilas...');
        try {
            const response = await fetch(ajaxBase + 'get_upazilas.php?district_id=' + encodeURIComponent(districtId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Upazila request failed');
            upazila.innerHTML = '<option value="">Any upazila</option>' + (await response.text()).replace(/^<option value="">Select Upazila<\/option>/, '');
            upazila.disabled = false;
            if (preserve && selectedUpazila) upazila.value = selectedUpazila;
        } catch (error) {
            upazila.innerHTML = '<option value="">Could not load upazilas</option>';
            upazila.disabled = true;
        }
    }

    division.addEventListener('change', function () {
        loadDistricts(this.value, false);
    });
    district.addEventListener('change', function () {
        loadUpazilas(this.value, false);
    });

    if (division.value) loadDistricts(division.value, true);
});
