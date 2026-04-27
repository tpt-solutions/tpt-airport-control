// Data Table Component
export class DataTable {
  private container: HTMLElement;
  private data: any[];
  private columns: Array<{ key: string; label: string; sortable?: boolean }>;
  private onRowClick?: (row: any) => void;

  constructor(
    container: HTMLElement,
    data: any[],
    columns: Array<{ key: string; label: string; sortable?: boolean }>,
    onRowClick?: (row: any) => void
  ) {
    this.container = container;
    this.data = data;
    this.columns = columns;
    this.onRowClick = onRowClick;
  }

  render() {
    this.container.innerHTML = `
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                ${this.columns.map(col => `
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${col.sortable ? 'cursor-pointer hover:bg-gray-100' : ''}" data-sort="${col.key}">
                    ${col.label}
                    ${col.sortable ? '<span class="ml-1">↕️</span>' : ''}
                  </th>
                `).join('')}
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              ${this.data.map((row, index) => `
                <tr class="hover:bg-gray-50 ${this.onRowClick ? 'cursor-pointer' : ''}" data-row-index="${index}">
                  ${this.columns.map(col => `
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      ${this.formatCellValue(row[col.key], col.key)}
                    </td>
                  `).join('')}
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>

        ${this.data.length === 0 ? `
          <div class="text-center py-8 text-gray-500">
            No data available
          </div>
        ` : ''}
      </div>
    `;

    this.setupEventListeners();
  }

  private formatCellValue(value: any, key: string): string {
    if (value === null || value === undefined) {
      return '-';
    }

    // Format dates
    if (key.includes('date') || key.includes('time') || key.includes('_at')) {
      try {
        return new Date(value).toLocaleString();
      } catch {
        return value;
      }
    }

    // Format status
    if (key === 'status') {
      const statusColors: { [key: string]: string } = {
        'confirmed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'checked-in': 'bg-blue-100 text-blue-800',
        'scheduled': 'bg-gray-100 text-gray-800',
        'departed': 'bg-green-100 text-green-800',
        'arrived': 'bg-blue-100 text-blue-800'
      };

      const colorClass = statusColors[value.toLowerCase()] || 'bg-gray-100 text-gray-800';
      return `<span class="px-2 py-1 rounded-full text-xs font-medium ${colorClass}">${value}</span>`;
    }

    // Format currency
    if (key.includes('amount') || key.includes('price')) {
      return `$${parseFloat(value).toFixed(2)}`;
    }

    return value.toString();
  }

  private setupEventListeners() {
    // Row click handler
    if (this.onRowClick) {
      const rows = this.container.querySelectorAll('tbody tr');
      rows.forEach((row, index) => {
        row.addEventListener('click', () => {
          this.onRowClick!(this.data[index]);
        });
      });
    }

    // Sort handlers
    const sortableHeaders = this.container.querySelectorAll('th[data-sort]');
    sortableHeaders.forEach(header => {
      header.addEventListener('click', (e) => {
        const sortKey = (e.currentTarget as HTMLElement).dataset.sort;
        if (sortKey) {
          this.sortBy(sortKey);
        }
      });
    });
  }

  private sortBy(key: string) {
    this.data.sort((a, b) => {
      const aVal = a[key];
      const bVal = b[key];

      if (aVal < bVal) return -1;
      if (aVal > bVal) return 1;
      return 0;
    });

    this.render();
  }

  updateData(newData: any[]) {
    this.data = newData;
    this.render();
  }
}
