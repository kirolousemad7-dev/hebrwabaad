type SupplierFiltersProps = {
  specialties: string[]
  services: string[]
  locations: string[]
  categories: string[]
  selectedSpecialty: string | null
  selectedService: string | null
  selectedLocation: string | null
  selectedCategory: string | null
  featuredOnly: boolean
  query: string
  onSpecialty: (value: string | null) => void
  onService: (value: string | null) => void
  onLocation: (value: string | null) => void
  onCategory: (value: string | null) => void
  onFeaturedOnly: (value: boolean) => void
  onQuery: (value: string) => void
}

function chipClass(active: boolean): string {
  return [
    'shrink-0 rounded-full px-4 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900',
    active ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700',
  ].join(' ')
}

export function SupplierFilters({
  specialties,
  services,
  locations,
  categories,
  selectedSpecialty,
  selectedService,
  selectedLocation,
  selectedCategory,
  featuredOnly,
  query,
  onSpecialty,
  onService,
  onLocation,
  onCategory,
  onFeaturedOnly,
  onQuery,
}: SupplierFiltersProps) {
  return (
    <section className="space-y-4" aria-labelledby="supplier-filters-heading">
      <div className="space-y-1">
        <h2 id="supplier-filters-heading" className="text-xl font-semibold">
          اكتشف الموردين
        </h2>
        <p className="text-sm text-slate-600">صفِّ حسب التصنيف أو الخدمة أو الموقع، أو اعرض المميزين فقط.</p>
      </div>

      <label className="block space-y-1 text-sm">
        <span>بحث</span>
        <input
          type="search"
          value={query}
          onChange={(event) => onQuery(event.target.value)}
          className="w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          placeholder="اسم المورد أو المدينة"
        />
      </label>

      <div>
        <button type="button" aria-pressed={featuredOnly} onClick={() => onFeaturedOnly(!featuredOnly)} className={chipClass(featuredOnly)}>
          المميزون فقط
        </button>
      </div>

      {categories.length > 0 ? (
        <div className="space-y-2">
          <p className="text-sm font-medium">التصنيف</p>
          <div className="flex gap-2 overflow-x-auto pb-1" role="toolbar" aria-label="تصفية التصنيف">
            <button type="button" aria-pressed={selectedCategory === null} onClick={() => onCategory(null)} className={chipClass(selectedCategory === null)}>
              الكل
            </button>
            {categories.map((category) => (
              <button
                key={category}
                type="button"
                aria-pressed={selectedCategory === category}
                onClick={() => onCategory(category)}
                className={chipClass(selectedCategory === category)}
              >
                {category}
              </button>
            ))}
          </div>
        </div>
      ) : null}

      {locations.length > 0 ? (
        <div className="space-y-2">
          <p className="text-sm font-medium">الموقع</p>
          <div className="flex gap-2 overflow-x-auto pb-1" role="toolbar" aria-label="تصفية الموقع">
            <button type="button" aria-pressed={selectedLocation === null} onClick={() => onLocation(null)} className={chipClass(selectedLocation === null)}>
              الكل
            </button>
            {locations.map((location) => (
              <button
                key={location}
                type="button"
                aria-pressed={selectedLocation === location}
                onClick={() => onLocation(location)}
                className={chipClass(selectedLocation === location)}
              >
                {location}
              </button>
            ))}
          </div>
        </div>
      ) : null}

      {specialties.length > 0 ? (
        <div className="space-y-2">
          <p className="text-sm font-medium">التخصص</p>
          <div className="flex gap-2 overflow-x-auto pb-1" role="toolbar" aria-label="تصفية التخصص">
            <button type="button" aria-pressed={selectedSpecialty === null} onClick={() => onSpecialty(null)} className={chipClass(selectedSpecialty === null)}>
              الكل
            </button>
            {specialties.map((specialty) => (
              <button
                key={specialty}
                type="button"
                aria-pressed={selectedSpecialty === specialty}
                onClick={() => onSpecialty(specialty)}
                className={chipClass(selectedSpecialty === specialty)}
              >
                {specialty}
              </button>
            ))}
          </div>
        </div>
      ) : null}

      {services.length > 0 ? (
        <div className="space-y-2">
          <p className="text-sm font-medium">الخدمة</p>
          <div className="flex gap-2 overflow-x-auto pb-1" role="toolbar" aria-label="تصفية الخدمة">
            <button type="button" aria-pressed={selectedService === null} onClick={() => onService(null)} className={chipClass(selectedService === null)}>
              الكل
            </button>
            {services.map((service) => (
              <button
                key={service}
                type="button"
                aria-pressed={selectedService === service}
                onClick={() => onService(service)}
                className={chipClass(selectedService === service)}
              >
                {service}
              </button>
            ))}
          </div>
        </div>
      ) : null}
    </section>
  )
}
