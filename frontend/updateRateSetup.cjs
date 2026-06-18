const fs = require('fs');

let content = fs.readFileSync('src/pages/config/components/RateSetup.vue', 'utf8');

// 1. Replace roomTypes
content = content.replace(/const roomTypes = ref\(\[[\s\S]*?\]\)/, 'const roomTypes = ref([])');

// 2. Replace occupancies
content = content.replace(/const occupancies = \['Double', 'Twin', 'Triple', 'Family', 'King'\]/, 'const occupancies = ref([])');

// 3. Add fetchRoomData
const fetchRoomDataFunc = `const fetchRoomData = async () => {
  try {
    const [classesRes, formsRes] = await Promise.all([
      http.get('/room-classes'),
      http.get('/room-forms')
    ])
    if (classesRes.status === 200) {
      roomTypes.value = classesRes.data.data.map(item => ({
        code: item.code,
        description: item.name
      }))
    }
    if (formsRes.status === 200) {
      occupancies.value = formsRes.data.data.map(item => item.name)
    }
  } catch (e) {
    console.error(e)
  }
}
`;

if (!content.includes('fetchRoomData')) {
  content = content.replace(/const fetchRateCodes = async/, fetchRoomDataFunc + '\nconst fetchRateCodes = async');
}

// 4. Call fetchRoomData inside onMounted
content = content.replace(/onMounted\(\(\) => \{\n\s*fetchRateCodes\(\)/, 'onMounted(() => {\n  fetchRoomData()\n  fetchRateCodes()');

// 5. Replace Grid Headers
const headersRegex = /<th class="p-2\.5 border border-slate-200 w-32">Double<\/th>[\s\S]*?<th class="p-2\.5 border border-slate-200 w-32">King<\/th>/;
const newHeaders = '<th v-for="occ in occupancies" :key="occ" class="p-2.5 border border-slate-200 w-32">{{ occ }}</th>';
content = content.replace(headersRegex, newHeaders);

// 6. Replace Grid Body Inputs
const inputsRegex = /<td class="p-1 border border-slate-200">\s*<input[^>]*:value="formatCurrencyInput\(rateMatrix\[getMatrixKey\(rt\.code, 'Double'\)\][\s\S]*?<\/td>\s*<td class="p-1 border border-slate-200">\s*<input[^>]*:value="formatCurrencyInput\(rateMatrix\[getMatrixKey\(rt\.code, 'King'\)\][\s\S]*?<\/td>/;

const newInputs = `<td v-for="occ in occupancies" :key="rt.code + '-' + occ" class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      :value="formatCurrencyInput(rateMatrix[getMatrixKey(rt.code, occ)], rateFormState.Currency)"
                      @input="e => rateMatrix[getMatrixKey(rt.code, occ)] = cleanCurrencyValue(e.target.value, rateFormState.Currency)"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white transition-colors"
                    />
                  </td>`;
                  
content = content.replace(inputsRegex, newInputs);

fs.writeFileSync('src/pages/config/components/RateSetup.vue', content);
console.log('Update finished.');
