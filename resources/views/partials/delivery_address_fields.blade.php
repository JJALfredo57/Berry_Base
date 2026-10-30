@php
  $prefix = $prefix ?? 'delivery';
  $title = $title ?? 'Delivery Address Details';
  $mapWrapId = $mapWrapId ?? '';
  $mapBtnId = $mapBtnId ?? '';
  $coverage = $deliveryCoverage ?? ['strict' => false, 'zone_count' => 0, 'provinces' => []];
  $coverageIsStrict = !empty($coverage['strict']) && !empty($coverage['provinces']);
@endphp
@once
<style>
.map-lock-wrap{position:relative}
.map-lock-wrap .map-lock-msg{display:none;position:absolute;inset:0;z-index:1200;align-items:center;justify-content:center;text-align:center;padding:1rem;border-radius:.9rem;background:rgba(248,250,252,.82);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);color:#475569;font-size:.85rem;font-weight:600;cursor:not-allowed}
.map-lock-wrap.is-locked .map-lock-msg{display:flex}
.delivery-coverage-guide{border:1px solid color-mix(in srgb,var(--primary) 20%,#dbeafe);background:linear-gradient(135deg,var(--primary-bg,#fff7fb),#fff);border-radius:.8rem;padding:.75rem .85rem;margin-bottom:.8rem;font-size:.8rem;color:#475569}
.delivery-coverage-guide strong{color:var(--primary-dark,var(--primary))}
.delivery-coverage-feedback{display:none;margin-top:.65rem;padding:.6rem .7rem;border-radius:.65rem;font-size:.78rem;font-weight:600}
.delivery-coverage-feedback.show{display:flex;align-items:flex-start;justify-content:space-between;gap:.6rem}
.delivery-coverage-feedback.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.delivery-coverage-feedback.success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.delivery-locked-field{background:var(--primary-bg,#fff7fb)!important;color:var(--primary-dark,var(--primary));font-weight:700}
@media(max-width:575.98px){.delivery-coverage-feedback.show{flex-direction:column}.delivery-coverage-feedback .btn{width:100%;min-height:40px}.delivery-coverage-guide{padding:.7rem}}
</style>
@endonce
<input type="hidden" name="_structured_address" value="1">
<div class="delivery-address-fields rounded-3 p-3 mb-3 js-psgc-address" style="background:#f8fafc;border:1px solid #e5e7eb" data-prefix="{{ $prefix }}" data-map-wrap="{{ $mapWrapId }}" data-map-btn="{{ $mapBtnId }}" data-coverage-id="{{ $prefix }}CoverageData">
  <script type="application/json" id="{{ $prefix }}CoverageData">@json($coverage)</script>
  <div class="fw-bold small mb-2"><i class="bi bi-geo-alt me-1" style="color:var(--primary)"></i>{{ $title }}</div>
  <div class="delivery-coverage-guide">
    <strong><i class="bi bi-shield-check me-1"></i>Seller delivery coverage</strong>
    <div class="mt-1">{{ $coverageIsStrict ? 'Only provinces, cities, and barangays served by this shop are available below. Your exact map pin will still be checked.' : 'Choose your official address, then pin the exact location. Delivery is approved only when the pin is inside this shop\'s active coverage.' }}</div>
  </div>
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
      <input class="form-control js-psgc-province" name="address_province" id="{{ $prefix }}AddressProvince" data-error-target="province" value="{{ old('address_province') }}" list="{{ $prefix }}ProvinceList" autocomplete="off" placeholder="Type to search covered province">
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
  <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Province, city/municipality, and barangay must match this seller's available coverage. Choose an exact suggestion.</div>
  <div class="delivery-coverage-feedback" data-coverage-feedback role="status" aria-live="polite"></div>
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
    const coverageNode = document.getElementById(block.dataset.coverageId || '');
    let coverage = { strict: false, provinces: [] };
    try { coverage = JSON.parse(coverageNode?.textContent || '{}'); } catch (e) {}
    const coverageProvinces = Array.isArray(coverage.provinces) ? coverage.provinces : [];
    const strictCoverage = coverage.strict === true && coverageProvinces.length > 0;
    const feedback = block.querySelector('[data-coverage-feedback]');
    const filterItems = (items, query) => {
      const needle = (query || '').trim().toLowerCase();
      return needle ? items.filter(item => (item.name || '').toLowerCase().includes(needle)) : items;
    };
    const coveredProvince = code => coverageProvinces.find(item => String(item.code) === String(code));
    const coveredCity = (provinceValue, code) => (provinceValue?.cities || []).find(item => String(item.code) === String(code));
    const provinceItems = query => strictCoverage
      ? Promise.resolve(filterItems(coverageProvinces, query))
      : fetchItems(endpoints.provinces, { q: query || '' });
    const cityItems = query => {
      if (strictCoverage) return Promise.resolve(filterItems(coveredProvince(provinceCode.value)?.cities || [], query));
      return fetchItems(endpoints.cities, { province_code: provinceCode.value, q: query || '' });
    };
    const barangayItems = query => {
      if (strictCoverage) {
        const parentProvince = coveredProvince(provinceCode.value);
        return Promise.resolve(filterItems(coveredCity(parentProvince, cityCode.value)?.barangays || [], query));
      }
      return fetchItems(endpoints.barangays, { city_municipality_code: cityCode.value, q: query || '' });
    };
    const showFeedback = (type, message) => {
      if (!feedback) return;
      feedback.className = 'delivery-coverage-feedback show ' + type;
      feedback.innerHTML = '<span><i class="bi bi-' + (type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill') + ' me-1"></i>' + message + '</span>' +
        (type === 'error' ? '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="switchCoverageToPickup()">Switch to Pickup</button>' : '');
    };
    const clearFeedback = () => { if (feedback) feedback.className = 'delivery-coverage-feedback'; };
    const syncFieldState = (input, code, label) => {
      const hasText = input.value.trim() !== '';
      input.classList.toggle('is-invalid', hasText && !code);
      input.classList.toggle('is-valid', hasText && !!code);
      if (hasText && !code && strictCoverage) showFeedback('error', label + ' is outside this seller\'s delivery coverage.');
      else if (barangayCode.value) showFeedback('success', 'Address area is covered. Pin your exact location for final delivery validation.');
      else if (!hasText) clearFeedback();
    };

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
      const complete = !!(provinceCode.value && cityCode.value && barangayCode.value);
      const wrap = block.dataset.mapWrap ? document.getElementById(block.dataset.mapWrap) : null;
      if (wrap) wrap.classList.toggle('is-locked', !complete);
      const mapBtn = block.dataset.mapBtn ? document.getElementById(block.dataset.mapBtn) : null;
      if (mapBtn) mapBtn.disabled = !complete;
      block.dispatchEvent(new CustomEvent('psgc-sync', { bubbles: true, detail: { prefix, complete } }));
    };
    const resolveCode = async (input, codeEl, list, loader) => {
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
          const items = await loader(value);
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
      const code = await resolveCode(province, provinceCode, provinceList, provinceItems);
      syncFieldState(province, code, 'Province');
      syncAvailability();
      return code;
    };
    const resolveCity = async () => {
      if (!provinceCode.value) await resolveProvince();
      if (!provinceCode.value) { cityCode.value = ''; syncAvailability(); return ''; }
      const code = await resolveCode(city, cityCode, cityList, cityItems);
      syncFieldState(city, code, 'City / municipality');
      syncAvailability();
      return code;
    };
    const resolveBarangay = async () => {
      if (!cityCode.value) await resolveCity();
      if (!cityCode.value) { barangayCode.value = ''; syncAvailability(); return ''; }
      const code = await resolveCode(barangay, barangayCode, barangayList, barangayItems);
      syncFieldState(barangay, code, 'Barangay');
      syncAvailability();
      return code;
    };
    const loadProvinces = debounce(async () => fillList(provinceList, await provinceItems(province.value || '')));
    const loadCities = debounce(async () => {
      if (!provinceCode.value) await resolveProvince();
      if (!provinceCode.value) return;
      fillList(cityList, await cityItems(city.value || ''));
    });
    const loadBarangays = debounce(async () => {
      if (!cityCode.value) await resolveCity();
      if (!cityCode.value) return;
      fillList(barangayList, await barangayItems(barangay.value || ''));
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
      if (strictCoverage && coverageProvinces.length === 1 && !province.value.trim()) {
        const onlyProvince = coverageProvinces[0];
        fillList(provinceList, coverageProvinces);
        province.value = onlyProvince.name;
        provinceCode.value = onlyProvince.code;
        province.readOnly = true;
        province.classList.add('delivery-locked-field', 'is-valid');
        fillList(cityList, onlyProvince.cities || []);
        syncAvailability();
        return;
      }
      if (province.value.trim() && await resolveProvince()) {
        if (strictCoverage && coverageProvinces.length === 1 && provinceCode.value === String(coverageProvinces[0].code)) {
          province.readOnly = true;
          province.classList.add('delivery-locked-field');
        }
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
  window.switchCoverageToPickup = function () {
    const pickup = document.querySelector('input[name="fulfillment_type"][value="Pickup"]');
    if (!pickup) return;
    pickup.checked = true;
    pickup.dispatchEvent(new Event('change', { bubbles: true }));
    pickup.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.js-psgc-address').forEach(initBlock));
})();
</script>
@endpush
@endonce
