// Modal Component
export class Modal {
  private modal!: HTMLElement;
  private isOpen: boolean = false;

  constructor() {
    this.createModal();
  }

  private createModal() {
    this.modal = document.createElement('div');
    this.modal.className = 'fixed inset-0 z-50 hidden';
    this.modal.innerHTML = `
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" id="modal-backdrop"></div>
        <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg sm:max-w-lg" id="modal-content">
          <div id="modal-header">
            <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">Modal Title</h3>
          </div>
          <div id="modal-body" class="mt-4">
            Modal content goes here
          </div>
          <div id="modal-footer" class="mt-6 flex justify-end space-x-3">
            <button id="modal-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
              Cancel
            </button>
            <button id="modal-confirm" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
              Confirm
            </button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(this.modal);
    this.setupEventListeners();
  }

  private setupEventListeners() {
    const backdrop = this.modal.querySelector('#modal-backdrop') as HTMLElement;
    const cancelBtn = this.modal.querySelector('#modal-cancel') as HTMLElement;

    backdrop?.addEventListener('click', () => this.close());
    cancelBtn?.addEventListener('click', () => this.close());
  }

  open(title: string, content: string, onConfirm?: () => void) {
    const titleEl = this.modal.querySelector('#modal-title') as HTMLElement;
    const bodyEl = this.modal.querySelector('#modal-body') as HTMLElement;
    const confirmBtn = this.modal.querySelector('#modal-confirm') as HTMLElement;

    if (titleEl) titleEl.textContent = title;
    if (bodyEl) bodyEl.innerHTML = content;

    if (onConfirm) {
      confirmBtn.addEventListener('click', () => {
        onConfirm();
        this.close();
      });
    }

    this.modal.classList.remove('hidden');
    this.isOpen = true;
  }

  close() {
    this.modal.classList.add('hidden');
    this.isOpen = false;
  }

  isVisible(): boolean {
    return this.isOpen;
  }
}
