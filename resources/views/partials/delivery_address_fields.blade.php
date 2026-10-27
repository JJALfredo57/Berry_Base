@php
  $prefix = $prefix ?? 'delivery';
  $title = $title ?? 'Delivery Address Details';
@endphp
<input type="hidden" name="_structured_address" value="1">
<div class="delivery-address-fields rounded-3 p-3 mb-3 js-psgc-address" style="background:#f8fafc;border:1px solid #e5e7eb" data-prefix="{{ $prefix }}">
  <div class="fw-bold small mb-2"><i class="bi bi-geo-alt me-1" style="color:var(--primary)"></i>{{ $title }}</div>
  <div class="row g-2">
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">House / Unit / Building No.</label>
      <input class="form-control" name="address_house" id="{{ $prefix }}AddressHouse" value="{{ old('address_house') }}" placeholder="House 12, Unit 3B">
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">Street / Road</label>
      <input class="form-control" name="address_street" id="{{ $prefix }}AddressStreet" value="{{ old('address_street') }}" placeholder="Rizal Street">
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">Subdivision / Village / Purok / Sitio</label>
      <input class="form-control" name="address_subdivision" id="{{ $prefix }}AddressSubdivision" value="{{ old('address_subdivision') }}" placeholder="Purok 2 or Villa Subdivision">
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">Province</label>
      <input class="form-control js-psgc-province" name="address_province" id="{{ $prefix }}AddressProvince" data-error-target="province" value="{{ old('address_province') }}" list="{{ $prefix }}ProvinceList" autocomplete="off" placeholder="Type to search province">
      <input type="hidden" name="address_province_code" id="{{ $prefix }}AddressProvinceCode" value="{{ old('address_province_code') }}">
      <datalist id="{{ $prefix }}ProvinceList"></datalist>
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">City / Municipality</label>
      <input class="form-control js-psgc-city" name="address_city" id="{{ $prefix }}AddressCity" data-error-target="city" value="{{ old('address_city') }}" list="{{ $prefix }}CityList" autocomplete="off" placeholder="Choose province first" disabled>
      <input type="hidden" name="address_city_code" id="{{ $prefix }}AddressCityCode" value="{{ old('address_city_code') }}">
      <datalist id="{{ $prefix }}CityList"></datalist>
    </div>
    <div class="col-sm-6">
      <label class="form-label small fw-semibold">Barangay</label>
      <input class="form-control js-psgc-barangay" name="address_barangay" id="{{ $prefix }}AddressBarangay" data-error-target="barangay" value="{{ old('address_barangay') }}" list="{{ $prefix }}BarangayList" autocomplete="off" placeholder="Choose city/municipality first" disabled>
      <input type="hidden" name="address_barangay_code" id="{{ $prefix }}AddressBarangayCode" value="{{ old('address_barangay_code') }}">
      <datalist id="{{ $prefix }}BarangayList"></datalist>
    </div>
    <div class="col-12">
      <label class="form-label small fw-semibold">Landmark</label>
      <input class="form-control" name="address_landmark" id="{{ $prefix }}AddressLandmark" value="{{ old('address_landmark') }}" placeholder="Near 7-Eleven, blue gate, beside chapel">
    </div>
  </div>
  <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Province, city/municipality, and barangay must match the official list. Choose a matching suggestion.</div>
</div>

@once
@push('scripts')
<script>
(function() {
  const endpoints = {
    provinces: @json(route('api.psgc.provinces')),
    cities: @json(route('api.psgc.cities_municipalities')),
    barangays: @json(route('api.psgc.barangays'))
  };
  const cache = new Map();
  const requestVersions = new WeakMap();
  const debounce = (fn, wait = 250) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), wait);
    };
  };
  const fetchItems = async (url, params = {}) => {
    const query = new URLSearchParams(params);
    const key = url + '?' + query.toString();
    if (cache.has(key)) return cache.get(key);
    const res = await fetch(key, { headers: { 'Accept': 'application/json' } });
    const data = res.ok ? await res.json() : { items: [] };
    const items = Array.isArray(data.items) ? data.items : [];
    cache.set(key, items);
    return items;
  };
  const fillList = (list, items) => {
    list.innerHTML = '';
    items.forEach(item => {
      const option = document.createElement('option');
      option.value = item.name || '';
      option.dataset.code = item.code || '';
      list.appendChild(option);
    });
  };
  const findCode = (list, value) => {
    const needle = (value || '').trim().toLowerCase();
    const match = Array.from(list.options).find(option => (option.value || '').trim().toLowerCase() === needle);
    return match ? match.dataset.code || '' : '';
  };
  const initBlock = block => {
    const prefix = block.dataset.prefix || 'delivery';
    const province = block.querySelector('.js-psgc-province');
    const city = block.querySelector('.js-psgc-city');
    const barangay = block.querySelector('.js-psgc-barangay');
    const provinceCode = document.getElementById(prefix + 'AddressProvinceCode');
    const cityCode = document.getElementById(prefix + 'AddressCityCode');
    const barangayCode = document.getElementById(prefix + 'AddressBarangayCode');
    const provinceList = document.getElementById(prefix + 'ProvinceList');
    const cityList = document.getElementById(prefix + 'CityList');
    const barangayList = document.getElementById(prefix + 'BarangayList');
    if (!province || !city || !barangay || !provinceCode || !cityCode || !barangayCode) return;

    const clearChildCodes = level => {
      if (level === 'province') {
        city.value = '';
        cityCode.value = '';
        cityList.innerHTML = '';
      }
      if (level === 'province' || level === 'city') {
        barangay.value = '';
        barangayCode.value = '';
        barangayList.innerHTML = '';
      }
      syncAvailability();
    };
    const syncAvailability = () => {
      city.disabled = !provinceCode.value;
      barangay.disabled = !cityCode.value;
      if (city.disabled) city.placeholder = 'Enter a valid province first';
      else city.placeholder = 'Type or choose a city/municipality';
      if (barangay.disabled) barangay.placeholder = 'Choose a valid city/municipality first';
      else barangay.placeholder = 'Type or choose a barangay';
    };
    const resolveCode = async (input, codeEl, list, url, params) => {
      const value = (input.value || '').trim();
      const version = (requestVersions.get(input) || 0) + 1;
      requestVersions.set(input, version);
      if (!value) {
        codeEl.value = '';
        return '';
      }
      let code = findCode(list, value);
      if (!code) {
        try {
          const items = await fetchItems(url, Object.assign({ q: value }, params));
          if (input.value.trim() !== value || requestVersions.get(input) !== version) return '';
          fillList(list, items);
          code = findCode(list, value);
        } catch (e) {
          if (input.value.trim() === value && requestVersions.get(input) === version) codeEl.value = '';
          return '';
        }
      }
      if (input.value.trim() !== value || requestVersions.get(input) !== version) return '';
      codeEl.value = code;
      return code;
    };
    const resolveProvince = async () => {
      const code = await resolveCode(province, provinceCode, provinceList, endpoints.provinces, {});
      syncAvailability();
      return code;
    };
    const resolveCity = async () => {
      if (!provinceCode.value) await resolveProvince();
      if (!provinceCode.value) { cityCode.value = ''; syncAvailability(); return ''; }
      const code = await resolveCode(city, cityCode, cityList, endpoints.cities, { province_code: provinceCode.value });
      syncAvailability();
      return code;
    };
    const resolveBarangay = async () => {
      if (!cityCode.value) await resolveCity();
      if (!cityCode.value) { barangayCode.value = ''; syncAvailability(); return ''; }
      const code = await resolveCode(barangay, barangayCode, barangayList, endpoints.barangays, { city_municipality_code: cityCode.value });
      syncAvailability();
      return code;
    };
    const loadProvinces = debounce(async () => fillList(provinceList, await fetchItems(endpoints.provinces, { q: province.value || '' })));
    const loadCities = debounce(async () => {
      if (!provinceCode.value) await resolveProvince();
      if (!provinceCode.value) return;
      fillList(cityList, await fetchItems(endpoints.cities, { province_code: provinceCode.value, q: city.value || '' }));
    });
    const loadBarangays = debounce(async () => {
      if (!cityCode.value) await resolveCity();
      if (!cityCode.value) return;
      fillList(barangayList, await fetchItems(endpoints.barangays, { city_municipality_code: cityCode.value, q: barangay.value || '' }));
    });

    province.addEventListener('focus', loadProvinces);
    province.addEventListener('input', () => {
      provinceCode.value = '';
      clearChildCodes('province');
      loadProvinces();
      resolveProvince();
    });
    province.addEventListener('change', async () => {
      await resolveProvince();
      clearChildCodes('province');
    });
    city.addEventListener('focus', loadCities);
    city.addEventListener('input', () => {
      cityCode.value = '';
      clearChildCodes('city');
      loadCities();
      resolveCity();
    });
    city.addEventListener('change', async () => {
      await resolveCity();
      clearChildCodes('city');
    });
    barangay.addEventListener('focus', loadBarangays);
    barangay.addEventListener('input', () => {
      loadBarangays();
      resolveBarangay();
    });
    barangay.addEventListener('change', resolveBarangay);

    syncAvailability();
    (async () => {
      provinceCode.value = '';
      cityCode.value = '';
      barangayCode.value = '';
      syncAvailability();
      if (province.value.trim() && await resolveProvince()) {
        if (city.value.trim() && await resolveCity()) {
          if (barangay.value.trim()) await resolveBarangay();
        } else {
          clearChildCodes('city');
        }
      } else {
        clearChildCodes('province');
      }
    })();
  };
  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.js-psgc-address').forEach(initBlock));
})();
</script>
@endpush
@endonce
