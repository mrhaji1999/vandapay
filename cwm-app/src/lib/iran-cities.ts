// لیست استان‌ها و شهرهای ایران
export interface City {
  id: number;
  name: string;
}

export interface Province {
  id: number;
  name: string;
  cities: City[];
}

export const iranProvinces: Province[] = [
  {
    id: 1,
    name: 'آذربایجان شرقی',
    cities: [
      { id: 101, name: 'تبریز' },
      { id: 102, name: 'مراغه' },
      { id: 103, name: 'میانه' },
      { id: 104, name: 'شبستر' },
      { id: 105, name: 'مرند' },
      { id: 106, name: 'بناب' },
      { id: 107, name: 'اسکو' },
      { id: 108, name: 'اهر' }
    ]
  },
  {
    id: 2,
    name: 'آذربایجان غربی',
    cities: [
      { id: 201, name: 'ارومیه' },
      { id: 202, name: 'خوی' },
      { id: 203, name: 'مهاباد' },
      { id: 204, name: 'میاندوآب' },
      { id: 205, name: 'بوکان' },
      { id: 206, name: 'سلماس' },
      { id: 207, name: 'نقده' }
    ]
  },
  {
    id: 3,
    name: 'اردبیل',
    cities: [
      { id: 301, name: 'اردبیل' },
      { id: 302, name: 'پارس آباد' },
      { id: 303, name: 'خلخال' },
      { id: 304, name: 'مشگین شهر' },
      { id: 305, name: 'گرمی' }
    ]
  },
  {
    id: 4,
    name: 'اصفهان',
    cities: [
      { id: 401, name: 'اصفهان' },
      { id: 402, name: 'کاشان' },
      { id: 403, name: 'خمینی شهر' },
      { id: 404, name: 'نجف آباد' },
      { id: 405, name: 'شاهین شهر' },
      { id: 406, name: 'فولادشهر' },
      { id: 407, name: 'نطنز' },
      { id: 408, name: 'اردستان' }
    ]
  },
  {
    id: 5,
    name: 'البرز',
    cities: [
      { id: 501, name: 'کرج' },
      { id: 502, name: 'هشتگرد' },
      { id: 503, name: 'فردیس' },
      { id: 504, name: 'ساوجبلاغ' },
      { id: 505, name: 'نظرآباد' }
    ]
  },
  {
    id: 6,
    name: 'ایلام',
    cities: [
      { id: 601, name: 'ایلام' },
      { id: 602, name: 'دهلران' },
      { id: 603, name: 'آبدانان' },
      { id: 604, name: 'دره شهر' }
    ]
  },
  {
    id: 7,
    name: 'بوشهر',
    cities: [
      { id: 701, name: 'بوشهر' },
      { id: 702, name: 'برازجان' },
      { id: 703, name: 'گناوه' },
      { id: 704, name: 'دیر' },
      { id: 705, name: 'کنگان' }
    ]
  },
  {
    id: 8,
    name: 'تهران',
    cities: [
      { id: 801, name: 'تهران' },
      { id: 802, name: 'اسلامشهر' },
      { id: 803, name: 'ورامین' },
      { id: 804, name: 'شهریار' },
      { id: 805, name: 'ملارد' },
      { id: 806, name: 'پاکدشت' },
      { id: 807, name: 'قدس' },
      { id: 808, name: 'ری' }
    ]
  },
  {
    id: 9,
    name: 'چهارمحال و بختیاری',
    cities: [
      { id: 901, name: 'شهرکرد' },
      { id: 902, name: 'بروجن' },
      { id: 903, name: 'فارسان' },
      { id: 904, name: 'لردگان' }
    ]
  },
  {
    id: 10,
    name: 'خراسان جنوبی',
    cities: [
      { id: 1001, name: 'بیرجند' },
      { id: 1002, name: 'قائن' },
      { id: 1003, name: 'فردوس' },
      { id: 1004, name: 'طبس' }
    ]
  },
  {
    id: 11,
    name: 'خراسان رضوی',
    cities: [
      { id: 1101, name: 'مشهد' },
      { id: 1102, name: 'نیشابور' },
      { id: 1103, name: 'سبزوار' },
      { id: 1104, name: 'تربت حیدریه' },
      { id: 1105, name: 'قوچان' },
      { id: 1106, name: 'تایباد' },
      { id: 1107, name: 'کاشمر' }
    ]
  },
  {
    id: 12,
    name: 'خراسان شمالی',
    cities: [
      { id: 1201, name: 'بجنورد' },
      { id: 1202, name: 'اسفراین' },
      { id: 1203, name: 'شیروان' },
      { id: 1204, name: 'آشخانه' }
    ]
  },
  {
    id: 13,
    name: 'خوزستان',
    cities: [
      { id: 1301, name: 'اهواز' },
      { id: 1302, name: 'آبادان' },
      { id: 1303, name: 'خرمشهر' },
      { id: 1304, name: 'دزفول' },
      { id: 1305, name: 'اندیمشک' },
      { id: 1306, name: 'ماهشهر' },
      { id: 1307, name: 'بهبهان' },
      { id: 1308, name: 'شوشتر' }
    ]
  },
  {
    id: 14,
    name: 'زنجان',
    cities: [
      { id: 1401, name: 'زنجان' },
      { id: 1402, name: 'ابهر' },
      { id: 1403, name: 'خدابنده' },
      { id: 1404, name: 'قیدار' }
    ]
  },
  {
    id: 15,
    name: 'سمنان',
    cities: [
      { id: 1501, name: 'سمنان' },
      { id: 1502, name: 'شاهرود' },
      { id: 1503, name: 'گرمسار' },
      { id: 1504, name: 'دامغان' }
    ]
  },
  {
    id: 16,
    name: 'سیستان و بلوچستان',
    cities: [
      { id: 1601, name: 'زاهدان' },
      { id: 1602, name: 'چابهار' },
      { id: 1603, name: 'ایرانشهر' },
      { id: 1604, name: 'خاش' }
    ]
  },
  {
    id: 17,
    name: 'فارس',
    cities: [
      { id: 1701, name: 'شیراز' },
      { id: 1702, name: 'مرودشت' },
      { id: 1703, name: 'جهرم' },
      { id: 1704, name: 'کازرون' },
      { id: 1705, name: 'فسا' },
      { id: 1706, name: 'لارستان' },
      { id: 1707, name: 'داراب' }
    ]
  },
  {
    id: 18,
    name: 'قزوین',
    cities: [
      { id: 1801, name: 'قزوین' },
      { id: 1802, name: 'البرز' },
      { id: 1803, name: 'تاکستان' },
      { id: 1804, name: 'آبیک' }
    ]
  },
  {
    id: 19,
    name: 'قم',
    cities: [
      { id: 1901, name: 'قم' }
    ]
  },
  {
    id: 20,
    name: 'کردستان',
    cities: [
      { id: 2001, name: 'سنندج' },
      { id: 2002, name: 'مریوان' },
      { id: 2003, name: 'بانه' },
      { id: 2004, name: 'سقز' }
    ]
  },
  {
    id: 21,
    name: 'کرمان',
    cities: [
      { id: 2101, name: 'کرمان' },
      { id: 2102, name: 'رفسنجان' },
      { id: 2103, name: 'سیرجان' },
      { id: 2104, name: 'بم' },
      { id: 2105, name: 'جیرفت' }
    ]
  },
  {
    id: 22,
    name: 'کرمانشاه',
    cities: [
      { id: 2201, name: 'کرمانشاه' },
      { id: 2202, name: 'اسلام آباد غرب' },
      { id: 2203, name: 'پاوه' },
      { id: 2204, name: 'جوانرود' }
    ]
  },
  {
    id: 23,
    name: 'کهگیلویه و بویراحمد',
    cities: [
      { id: 2301, name: 'یاسوج' },
      { id: 2302, name: 'گچساران' },
      { id: 2303, name: 'دوگنبدان' }
    ]
  },
  {
    id: 24,
    name: 'گلستان',
    cities: [
      { id: 2401, name: 'گرگان' },
      { id: 2402, name: 'گنبد کاووس' },
      { id: 2403, name: 'علی آباد' },
      { id: 2404, name: 'آق قلا' }
    ]
  },
  {
    id: 25,
    name: 'گیلان',
    cities: [
      { id: 2501, name: 'رشت' },
      { id: 2502, name: 'انزلی' },
      { id: 2503, name: 'لاهیجان' },
      { id: 2504, name: 'لنگرود' },
      { id: 2505, name: 'رودسر' }
    ]
  },
  {
    id: 26,
    name: 'لرستان',
    cities: [
      { id: 2601, name: 'خرم آباد' },
      { id: 2602, name: 'بروجرد' },
      { id: 2603, name: 'دورود' },
      { id: 2604, name: 'الیگودرز' }
    ]
  },
  {
    id: 27,
    name: 'مازندران',
    cities: [
      { id: 2701, name: 'ساری' },
      { id: 2702, name: 'بابل' },
      { id: 2703, name: 'آمل' },
      { id: 2704, name: 'قائمشهر' },
      { id: 2705, name: 'بهشهر' },
      { id: 2706, name: 'بندر عباس' }
    ]
  },
  {
    id: 28,
    name: 'مرکزی',
    cities: [
      { id: 2801, name: 'اراک' },
      { id: 2802, name: 'ساوه' },
      { id: 2803, name: 'خمین' },
      { id: 2804, name: 'دلیجان' }
    ]
  },
  {
    id: 29,
    name: 'هرمزگان',
    cities: [
      { id: 2901, name: 'بندر عباس' },
      { id: 2902, name: 'قشم' },
      { id: 2903, name: 'کیش' },
      { id: 2904, name: 'بندر لنگه' }
    ]
  },
  {
    id: 30,
    name: 'همدان',
    cities: [
      { id: 3001, name: 'همدان' },
      { id: 3002, name: 'ملایر' },
      { id: 3003, name: 'تویسرکان' },
      { id: 3004, name: 'کبودرآهنگ' }
    ]
  },
  {
    id: 31,
    name: 'یزد',
    cities: [
      { id: 3101, name: 'یزد' },
      { id: 3102, name: 'اردکان' },
      { id: 3103, name: 'مهریز' },
      { id: 3104, name: 'ابرکوه' }
    ]
  }
];

export const getProvinceById = (id: number): Province | undefined => {
  return iranProvinces.find(p => p.id === id);
};

export const getCitiesByProvinceId = (provinceId: number): City[] => {
  const province = getProvinceById(provinceId);
  return province ? province.cities : [];
};

