const USERS_KEY = 'pnp-efds-prototype-users';
const DOCUMENTS_KEY = 'pnp-efds-prototype-documents';
const SESSION_KEY = 'pnp-efds-prototype-session';
const allowedViews = ['for_action', 'draft', 'within_office', 'outside_office', 'archived'];
const routeOptions = {
  within_office: [
    'Chief of Police', 'Deputy Chief of Police', 'C, Administrative Section',
    'C, Intelligence Section', 'C, Operation Section', 'C, Supply Section',
    'C, Municipal Community Affairs Section', 'C, Finance Section', 'C, Investigation Section',
  ],
  outside_office: [
    'Provincial Director', 'Deputy Provincial Director', 'C, Personnel & Records Mngt Unit',
    'C, Intelligence Unit', 'C, Operations Unit', "C, Logistics & Research Dev't Unit",
    "C, Community Affairs & Dev't Unit", 'C, Comptrollership Unit',
    'C, Investigation & Detective Mngt Unit', "C, Learning & Doctrine Dev't Unit",
    'C, Plans & Strategy Mngt Unit', 'C, ICT Mngt Unit',
  ],
};
const viewInfo = {
  for_action: ['For Action', 'Documents awaiting action'],
  draft: ['Draft', 'Documents saved for later completion'],
  within_office: ['Within Office', 'Documents moving within the office'],
  outside_office: ['Outside Office', 'Documents sent outside the office'],
  archived: ['Archived', 'Documents archived from For Action'],
};
const byId = (id) => document.getElementById(id);
let currentView = 'for_action';
let currentUser = null;
let currentDraftType = 'Correspondence';
let currentWithinType = '';
let selectedPreviewId = null;
let visibleDocuments = [];
let selectedIds = new Set();
let routingIds = [];
let routeNeedsDetails = false;

const today = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};
const escapeHTML = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
})[character]);
const readStore = (key, fallback) => {
  try {
    const value = localStorage.getItem(key);
    return value === null ? fallback : JSON.parse(value);
  } catch {
    return fallback;
  }
};
const writeStore = (key, value) => localStorage.setItem(key, JSON.stringify(value));

function seedPrototypeData() {
  const users = readStore(USERS_KEY, null);
  if (!Array.isArray(users) || users.length === 0) {
    writeStore(USERS_KEY, [{ name: 'Demo User', email: 'demo@pnp.gov.ph', password: 'password123' }]);
  }

  if (readStore(DOCUMENTS_KEY, null) === null) {
    writeStore(DOCUMENTS_KEY, [
      {
        id: 'sample-1001', subject: 'Monthly Operations Report', file_name: 'operations-report-june.docx',
        from_unit: 'Operations Section', to_unit: 'Chief of Police', document_type: 'Correspondence',
        stl_type: 'Memorandum', priority: 'Yes', date_in: '', action_requested: '', remarks: '',
        created_at: today(), status: 'for_action',
        document_content: '<h2>Monthly Operations Report</h2><p>Submitted for review and appropriate action.</p><p>Prepared by the Operations Section.</p>',
      },
      {
        id: 'sample-1002', subject: 'Radio Message - District Coordination', file_name: 'district-coordination.docx',
        from_unit: 'Provincial Director', to_unit: 'Communications Desk', document_type: 'PNP Radio Message',
        stl_type: 'Radio Message', priority: 'No', date_in: today(), action_requested: 'for Information', remarks: '',
        created_at: today(), status: 'for_action',
        document_content: '<h2>District Coordination</h2><p>Please coordinate the scheduled district briefing and confirm attendance.</p>',
      },
      {
        id: 'sample-1003', subject: 'Quarterly Equipment Inventory', file_name: 'equipment-inventory.docx',
        from_unit: 'Supply Section', to_unit: 'Finance Section', document_type: 'Investigation Report',
        stl_type: 'Report', priority: 'No', date_in: '', action_requested: '', remarks: '',
        created_at: today(), status: 'within_office',
        document_content: '<h2>Equipment Inventory</h2><p>Inventory records are attached for review.</p>',
      },
      {
        id: 'sample-1004', subject: 'Request for Regional Records', file_name: 'regional-records-request.docx',
        from_unit: 'Records Section', to_unit: 'Provincial Director', document_type: 'Correspondence',
        stl_type: 'Request', priority: 'Yes', date_in: '', action_requested: '', remarks: '',
        created_at: today(), status: 'outside_office',
        document_content: '<h2>Request for Regional Records</h2><p>Kindly provide the latest regional records for consolidation.</p>',
      },
      {
        id: 'sample-1005', subject: 'Completed Training Attendance', file_name: 'training-attendance.docx',
        from_unit: 'Admin Section', to_unit: 'Chief of Police', document_type: 'Correspondence',
        stl_type: 'Report', priority: 'No', date_in: today(), action_requested: 'for File/Reference', remarks: '',
        created_at: today(), status: 'archived',
        document_content: '<h2>Training Attendance</h2><p>Attendance documentation is complete and filed for reference.</p>',
      },
    ]);
  }

  const storedDocuments = readStore(DOCUMENTS_KEY, []);
  const documents = Array.isArray(storedDocuments) ? storedDocuments : [];
  const exampleDocuments = [
    {
      id: 'sample-1006', subject: 'Community Safety Coordination', file_name: 'community-safety-coordination.docx',
      from_unit: 'Municipal Community Affairs Section', to_unit: 'Chief of Police', document_type: 'Correspondence',
      stl_type: 'Memorandum', priority: 'Yes', date_in: today(), action_requested: 'for Approval', remarks: '',
      created_at: today(), status: 'for_action',
      document_content: '<h2>Community Safety Coordination</h2><p>Submitted for approval and coordination with the concerned units.</p>',
    },
    {
      id: 'sample-1007', subject: 'Station Facilities Maintenance Request', file_name: 'station-facilities-maintenance.docx',
      from_unit: 'Admin Section', to_unit: 'Supply Section', document_type: 'Correspondence',
      stl_type: 'Request', priority: 'No', date_in: today(), action_requested: 'for Recommendation', remarks: '',
      created_at: today(), status: 'within_office',
      document_content: '<h2>Facilities Maintenance Request</h2><p>Please inspect the station facilities and recommend the required maintenance work.</p>',
    },
    {
      id: 'sample-1008', subject: 'Provincial Training Schedule', file_name: 'provincial-training-schedule.docx',
      from_unit: 'Training Section', to_unit: 'Provincial Director', document_type: 'PNP Radio Message',
      stl_type: 'Radio Message', priority: 'No', date_in: '', action_requested: '', remarks: '',
      created_at: today(), status: 'outside_office',
      document_content: '<h2>Provincial Training Schedule</h2><p>Forwarding the proposed training schedule for review and confirmation.</p>',
    },
  ];
  const existingIds = new Set(documents.map((documentItem) => documentItem.id));
  const missingExamples = exampleDocuments.filter((documentItem) => !existingIds.has(documentItem.id));
  if (missingExamples.length > 0) {
    writeStore(DOCUMENTS_KEY, [...documents, ...missingExamples]);
  }
}

function getDocuments() {
  const documents = readStore(DOCUMENTS_KEY, []);
  return Array.isArray(documents) ? documents : [];
}

function setAuthMode(mode, notice = '') {
  const isRegister = mode === 'register';
  byId('authTitle').textContent = isRegister ? 'Create account' : 'Welcome back';
  byId('authIntro').textContent = isRegister
    ? 'Create an account to continue to the portal.'
    : 'Enter your credentials to access your account.';
  byId('loginForm').hidden = isRegister;
  byId('registerForm').hidden = !isRegister;
  byId('authSwitch').innerHTML = isRegister
    ? 'Already have an account? <button type="button" class="text-button" data-auth-mode="login">Sign in</button>'
    : 'Don\'t have an account? <button type="button" class="text-button" data-auth-mode="register">Create account</button>';
  byId('demoHint').hidden = isRegister;
  const noticeElement = byId('authNotice');
  noticeElement.textContent = notice;
  noticeElement.hidden = !notice;
  noticeElement.classList.toggle('error', Boolean(notice) && /invalid|already|valid|match/i.test(notice));
}

function showApp(user) {
  currentUser = user;
  document.title = 'PNP EFDS | Dashboard';
  byId('authScreen').hidden = true;
  byId('appScreen').hidden = false;
  byId('userName').textContent = user.name;
  byId('userAvatar').textContent = user.name.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase();
  byId('todayDate').textContent = new Intl.DateTimeFormat(undefined, { dateStyle: 'full' }).format(new Date());
  render();
}

function showAuth() {
  currentUser = null;
  document.title = 'PNP EFDS | Sign in';
  byId('appScreen').hidden = true;
  byId('authScreen').hidden = false;
  setAuthMode('login');
}

function setMessage(message) {
  const element = byId('statusMessage');
  element.textContent = message;
  element.hidden = !message;
  if (message) {
    window.clearTimeout(setMessage.timeout);
    setMessage.timeout = window.setTimeout(() => { element.hidden = true; }, 4200);
  }
}

function sanitizeMarkup(markup) {
  const allowed = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'STRIKE', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'H1', 'H2', 'H3', 'DIV']);
  const parsed = new DOMParser().parseFromString(markup, 'text/html');
  const cleanNode = (node) => {
    if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent);
    if (node.nodeType !== Node.ELEMENT_NODE) return document.createDocumentFragment();
    if (!allowed.has(node.tagName)) {
      const fragment = document.createDocumentFragment();
      [...node.childNodes].forEach((child) => fragment.append(cleanNode(child)));
      return fragment;
    }
    const clean = document.createElement(node.tagName.toLowerCase());
    if (['DIV', 'P', 'H1', 'H2', 'H3'].includes(node.tagName) && ['left', 'center', 'right', 'justify'].includes(node.getAttribute('align'))) {
      clean.setAttribute('align', node.getAttribute('align'));
    }
    [...node.childNodes].forEach((child) => clean.append(cleanNode(child)));
    return clean;
  };
  const result = document.createDocumentFragment();
  [...parsed.body.childNodes].forEach((child) => result.append(cleanNode(child)));
  const wrapper = document.createElement('div');
  wrapper.append(result);
  return wrapper.innerHTML;
}

function getFilters() {
  return {
    subject: byId('filterSubject').value.trim().toLowerCase(),
    fileName: byId('filterFileName').value.trim().toLowerCase(),
    created: byId('filterCreatedDate').value,
    received: byId('filterReceivedDate').value,
    type: byId('filterType').value,
  };
}

function matchingDocuments() {
  const filters = getFilters();
  const limit = Number(byId('entriesLimit').value) || 10;
  const docs = getDocuments().filter((documentItem) => {
    if (documentItem.status !== currentView) return false;
    if (filters.subject && !documentItem.subject.toLowerCase().includes(filters.subject)) return false;
    if (filters.fileName && !documentItem.file_name.toLowerCase().includes(filters.fileName)) return false;
    if (filters.created && documentItem.created_at !== filters.created) return false;
    if (filters.received && documentItem.date_in !== filters.received) return false;
    if (filters.type && documentItem.document_type !== filters.type) return false;
    return true;
  });
  docs.sort((left, right) => right.created_at.localeCompare(left.created_at) || left.subject.localeCompare(right.subject));
  return docs.slice(0, limit);
}

function renderPreview(documentItem) {
  const preview = byId('documentPreview');
  if (!documentItem) {
    preview.hidden = true;
    preview.innerHTML = '';
    return;
  }
  preview.hidden = false;
  preview.innerHTML = `
    <div class="document-preview-heading"><h3>${escapeHTML(documentItem.subject)}</h3><div><button class="secondary-button compact" type="button" data-download-id="${escapeHTML(documentItem.id)}">Download .docx</button><button class="text-button" type="button" id="closePreview">Close</button></div></div>
    <dl class="document-metadata">
      <div><dt>File name</dt><dd>${escapeHTML(documentItem.file_name)}</dd></div><div><dt>From</dt><dd>${escapeHTML(documentItem.from_unit || '-')}</dd></div>
      <div><dt>To</dt><dd>${escapeHTML(documentItem.to_unit || '-')}</dd></div><div><dt>Type</dt><dd>${escapeHTML(documentItem.document_type)}</dd></div>
      <div><dt>STL Type</dt><dd>${escapeHTML(documentItem.stl_type || '-')}</dd></div><div><dt>Priority</dt><dd>${escapeHTML(documentItem.priority || '-')}</dd></div>
      <div><dt>Date In</dt><dd>${escapeHTML(documentItem.date_in || '-')}</dd></div><div><dt>Action Required</dt><dd>${escapeHTML(documentItem.action_requested || '-')}</dd></div>
      ${documentItem.remarks ? `<div><dt>Remarks</dt><dd>${escapeHTML(documentItem.remarks).replace(/\n/g, '<br>')}</dd></div>` : ''}
    </dl><div class="document-body-preview">${sanitizeMarkup(documentItem.document_content || '<p>No document content.</p>')}</div>`;
}

function renderActions() {
  const container = byId('entriesActions');
  let buttons = '';
  if (currentView === 'for_action') {
    buttons += '<button class="secondary-button compact" type="button" data-bulk="receive">Receive</button>';
  }
  if (currentView !== 'within_office' || byId('receivedFilter').hidden) {
    buttons += '<button class="secondary-button compact" type="button" data-bulk="return">Return</button>';
  }
  if (currentView === 'for_action') {
    buttons += '<button class="danger-button compact" type="button" data-bulk="archive">Remove from For Action</button>';
  } else if (currentView === 'archived') {
    buttons += '<button class="success-button compact" type="button" data-bulk="restore">Restore to For Action</button>';
  }
  if (!(currentView === 'within_office' && byId('receivedFilter').hidden === false)) {
    buttons += '<button class="secondary-button compact" type="button" data-open-route="selected">Route</button>';
  }
  container.innerHTML = buttons;
}

function renderTable() {
  visibleDocuments = matchingDocuments();
  selectedIds = new Set([...selectedIds].filter((id) => visibleDocuments.some((item) => item.id === id)));
  byId('selectAll').checked = visibleDocuments.length > 0 && visibleDocuments.every((item) => selectedIds.has(item.id));
  byId('documentsBody').innerHTML = visibleDocuments.map((item) => `
    <tr>
      <td><input class="document-check" type="checkbox" data-select-id="${escapeHTML(item.id)}" aria-label="Select document" ${selectedIds.has(item.id) ? 'checked' : ''}></td>
      <td>${escapeHTML(item.subject)}</td><td>${escapeHTML(item.file_name)}</td><td>${escapeHTML(item.from_unit || '-')}</td>
      <td>${escapeHTML(item.to_unit || '-')}</td><td>${escapeHTML(item.document_type)}</td><td>${escapeHTML(item.stl_type || '-')}</td>
      <td>${escapeHTML(item.priority || '-')}</td><td>${escapeHTML(item.date_in || '-')}</td><td>${escapeHTML(item.action_requested || '-')}</td>
      <td><div class="tool-actions"><button class="tool-link" type="button" data-view-document="${escapeHTML(item.id)}">View</button>
      ${['for_action', 'within_office', 'outside_office'].includes(currentView) ? `<button class="route-tool-button" type="button" data-open-route="${escapeHTML(item.id)}">Route Within</button><button class="route-tool-button" type="button" data-open-route="${escapeHTML(item.id)}" data-office="outside_office">Route Outside</button>` : ''}</div></td>
    </tr>`).join('');
  byId('emptyState').hidden = visibleDocuments.length !== 0;
}

function render() {
  const [title, description] = viewInfo[currentView];
  byId('viewTitle').textContent = title;
  byId('viewDescription').textContent = currentView === 'within_office' && currentWithinType === 'received_documents'
    ? 'Documents received within the office'
    : description;
  byId('panelTitle').textContent = currentView === 'draft' && !byId('draftForm').hidden
    ? 'New Draft'
    : currentView === 'within_office' && currentWithinType === 'received_documents' ? 'Received Documents' : title;
  byId('newDraftButton').hidden = currentView !== 'draft' || !byId('draftForm').hidden;
  byId('filterForm').hidden = !byId('draftForm').hidden;
  byId('receivedFilter').hidden = currentView !== 'within_office' || currentWithinType !== 'received_documents';
  byId('draftType').value = currentDraftType;
  byId('entriesLimit').value = String(Math.min(Number(byId('entriesLimit').value) || 10, 100));
  document.querySelectorAll('[data-view]').forEach((button) => button.classList.toggle('active', button.dataset.view === currentView));
  document.querySelectorAll('.nav-menu').forEach((menu) => {
    menu.querySelector('summary').classList.toggle('active', menu.dataset.menuView === currentView);
  });
  document.querySelectorAll('[data-draft-type]').forEach((button) => {
    button.classList.toggle('active', currentView === 'draft' && button.dataset.draftType === currentDraftType);
  });
  document.querySelectorAll('[data-within-type]').forEach((button) => {
    button.classList.toggle('active', currentView === 'within_office' && button.dataset.withinType === currentWithinType);
  });
  renderPreview(getDocuments().find((item) => item.id === selectedPreviewId) || null);
  renderActions();
  renderTable();
}

function openRoute(ids, presetOffice = '') {
  routingIds = ids;
  routeNeedsDetails = currentView === 'for_action';
  byId('routeTarget').value = presetOffice || 'within_office';
  byId('routeDetails').hidden = !routeNeedsDetails;
  byId('routeRemarks').required = false;
  byId('actionRequired').required = routeNeedsDetails;
  byId('routeRemarks').value = '';
  byId('actionRequired').value = '';
  renderRouteOptions();
  byId('routeDialog').showModal();
}

function renderRouteOptions() {
  const target = byId('routeTarget').value;
  byId('routeDestinations').innerHTML = routeOptions[target].map((option, index) => `
    <label class="route-destination"><input type="radio" name="routeDestination" value="${escapeHTML(option)}" ${index === 0 ? 'required' : ''}>${escapeHTML(option)}</label>`).join('');
}

function downloadDocx(documentItem) {
  if (!documentItem || !documentItem.document_content || !documentItem.document_content.replace(/<[^>]*>/g, '').trim()) {
    window.alert('Add document content before downloading.');
    return;
  }
  if (!window.htmlDocx) {
    window.alert('Word export is unavailable. Check your internet connection and try again.');
    return;
  }
  const html = `<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Georgia,serif;font-size:12pt;line-height:1.6}</style></head><body><h1>${escapeHTML(documentItem.subject)}</h1><p><strong>From:</strong> ${escapeHTML(documentItem.from_unit || '-')}</p><p><strong>To:</strong> ${escapeHTML(documentItem.to_unit || '-')}</p><hr>${sanitizeMarkup(documentItem.document_content)}</body></html>`;
  const blob = window.htmlDocx.asBlob(html);
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${(documentItem.file_name || documentItem.subject || 'document').replace(/[\\/:*?"<>|]/g, '_').replace(/\.\w+$/, '')}.docx`;
  link.click();
  URL.revokeObjectURL(url);
}

seedPrototypeData();
setAuthMode('login');
const savedSession = readStore(SESSION_KEY, null);
if (savedSession?.email) {
  const storedUser = readStore(USERS_KEY, []).find((user) => user.email === savedSession.email);
  if (storedUser) showApp(storedUser);
}

byId('authScreen').addEventListener('click', (event) => {
  const modeButton = event.target.closest('[data-auth-mode]');
  if (modeButton) setAuthMode(modeButton.dataset.authMode);
});

byId('loginForm').addEventListener('submit', (event) => {
  event.preventDefault();
  const formData = new FormData(event.currentTarget);
  const email = String(formData.get('email')).trim().toLowerCase();
  const password = String(formData.get('password'));
  const user = readStore(USERS_KEY, []).find((item) => item.email.toLowerCase() === email && item.password === password);
  if (!user) {
    setAuthMode('login', 'Invalid email or password.');
    return;
  }
  writeStore(SESSION_KEY, { email: user.email });
  showApp(user);
});

byId('registerForm').addEventListener('submit', (event) => {
  event.preventDefault();
  const formData = new FormData(event.currentTarget);
  const name = String(formData.get('name')).trim();
  const email = String(formData.get('email')).trim().toLowerCase();
  const password = String(formData.get('password'));
  const users = readStore(USERS_KEY, []);
  if (users.some((user) => user.email.toLowerCase() === email)) {
    setAuthMode('register', 'That email is already registered.');
    return;
  }
  if (!name || !email.includes('@') || password.length < 8) {
    setAuthMode('register', 'Enter a name, valid email, and password with at least 8 characters.');
    return;
  }
  const user = { name, email, password };
  users.push(user);
  writeStore(USERS_KEY, users);
  writeStore(SESSION_KEY, { email });
  showApp(user);
  setMessage('Account created. You are signed in on this browser.');
});

byId('logoutButton').addEventListener('click', () => {
  localStorage.removeItem(SESSION_KEY);
  selectedIds.clear();
  showAuth();
});

document.querySelectorAll('[data-view]').forEach((button) => {
  button.addEventListener('click', () => {
    currentView = allowedViews.includes(button.dataset.view) ? button.dataset.view : 'for_action';
    currentWithinType = '';
    selectedIds.clear();
    selectedPreviewId = null;
    byId('draftForm').hidden = true;
    byId('draftForm').reset();
    byId('documentEditor').innerHTML = '';
    render();
  });
});

document.querySelector('.navigation').addEventListener('click', (event) => {
  const draftOption = event.target.closest('[data-draft-type]');
  const withinOption = event.target.closest('[data-within-type]');
  if (draftOption) {
    currentDraftType = draftOption.dataset.draftType;
    currentView = 'draft';
    byId('draftForm').reset();
    byId('draftType').value = currentDraftType;
    byId('documentEditor').innerHTML = '';
    byId('draftForm').hidden = false;
    selectedIds.clear();
  } else if (withinOption) {
    currentView = 'within_office';
    currentWithinType = withinOption.dataset.withinType;
    byId('draftForm').hidden = true;
    selectedIds.clear();
  } else {
    return;
  }
  event.target.closest('.nav-menu').open = false;
  selectedPreviewId = null;
  render();
});

document.addEventListener('click', (event) => {
  document.querySelectorAll('.navigation .nav-menu[open]').forEach((menu) => {
    if (!menu.contains(event.target)) menu.open = false;
  });
});

byId('newDraftButton').addEventListener('click', () => {
  byId('draftForm').reset();
  byId('draftType').value = currentDraftType;
  byId('documentEditor').innerHTML = '';
  byId('draftForm').hidden = false;
  byId('filterForm').hidden = true;
  byId('newDraftButton').hidden = true;
  byId('panelTitle').textContent = 'New Draft';
});
byId('cancelDraftButton').addEventListener('click', () => {
  byId('draftForm').hidden = true;
  byId('draftForm').reset();
  byId('documentEditor').innerHTML = '';
  render();
});
byId('draftForm').addEventListener('submit', (event) => {
  event.preventDefault();
  const formData = new FormData(event.currentTarget);
  const content = sanitizeMarkup(byId('documentEditor').innerHTML);
  if (!content.replace(/<[^>]*>/g, '').trim()) {
    window.alert('Add document content before saving.');
    return;
  }
  const destination = event.submitter?.value === 'for_action' ? 'for_action' : 'draft';
  const documentItem = {
    id: `doc-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    subject: String(formData.get('subject')).trim(), file_name: String(formData.get('file_name')).trim(),
    from_unit: String(formData.get('from_unit')).trim(), to_unit: String(formData.get('to_unit')).trim(),
    document_type: String(formData.get('document_type')), stl_type: String(formData.get('stl_type')).trim(),
    priority: String(formData.get('priority')), date_in: destination === 'for_action' ? today() : '', action_requested: '', remarks: '',
    created_at: today(), status: destination, document_content: content,
  };
  const documents = getDocuments();
  documents.push(documentItem);
  writeStore(DOCUMENTS_KEY, documents);
  byId('draftForm').hidden = true;
  byId('draftForm').reset();
  byId('documentEditor').innerHTML = '';
  currentView = destination;
  selectedIds.clear();
  render();
  setMessage(destination === 'draft' ? 'Draft saved in this browser.' : 'Document sent to For Action.');
});

document.querySelectorAll('.editor-tool').forEach((button) => {
  button.addEventListener('mousedown', (event) => event.preventDefault());
  button.addEventListener('click', () => {
    byId('documentEditor').focus();
    document.execCommand(button.dataset.command, false);
  });
});
byId('downloadDraftButton').addEventListener('click', () => downloadDocx({
  subject: byId('draftSubject').value || 'document', file_name: byId('draftFileName').value || 'document',
  from_unit: byId('draftFrom').value, to_unit: byId('draftTo').value, document_content: byId('documentEditor').innerHTML,
}));

byId('filterForm').addEventListener('submit', (event) => {
  event.preventDefault();
  selectedIds.clear();
  renderTable();
});
byId('clearFilterButton').addEventListener('click', () => {
  byId('filterForm').reset();
  selectedIds.clear();
  renderTable();
});
byId('entriesLimit').addEventListener('change', renderTable);
byId('selectAll').addEventListener('change', (event) => {
  visibleDocuments.forEach((item) => event.target.checked ? selectedIds.add(item.id) : selectedIds.delete(item.id));
  renderTable();
});
byId('documentsBody').addEventListener('change', (event) => {
  const checkbox = event.target.closest('[data-select-id]');
  if (!checkbox) return;
  if (checkbox.checked) selectedIds.add(checkbox.dataset.selectId);
  else selectedIds.delete(checkbox.dataset.selectId);
  renderTable();
});

byId('documentsBody').addEventListener('click', (event) => {
  const viewButton = event.target.closest('[data-view-document]');
  if (viewButton) {
    selectedPreviewId = viewButton.dataset.viewDocument;
    renderPreview(getDocuments().find((item) => item.id === selectedPreviewId));
    return;
  }
  const routeButton = event.target.closest('[data-open-route]');
  if (routeButton) {
    const id = routeButton.dataset.openRoute;
    openRoute(id === 'selected' ? [...selectedIds] : [id], routeButton.dataset.office || '');
  }
});
byId('entriesActions').addEventListener('click', (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  if (button.dataset.openRoute) {
    const ids = [...selectedIds];
    if (!ids.length) return window.alert('Select at least one document first.');
    openRoute(ids);
    return;
  }
  const action = button.dataset.bulk;
  if (!action) return;
  const ids = [...selectedIds];
  if (!ids.length) return window.alert('Select at least one document first.');
  if (action === 'return') {
    window.alert(`Return action selected for ${ids.length} document(s).`);
    return;
  }
  const documents = getDocuments();
  let changed = 0;
  documents.forEach((item) => {
    if (!ids.includes(item.id)) return;
    if (action === 'archive' && item.status === 'for_action') { item.status = 'archived'; changed += 1; }
    if (action === 'restore' && item.status === 'archived') { item.status = 'for_action'; changed += 1; }
    if (action === 'receive' && item.status === 'for_action' && !item.date_in) { item.date_in = today(); changed += 1; }
  });
  writeStore(DOCUMENTS_KEY, documents);
  selectedIds.clear();
  render();
  setMessage(changed ? `${changed} document(s) ${action === 'archive' ? 'archived' : action === 'restore' ? 'restored to For Action' : 'received'}.` : 'No selected documents were changed.');
});

byId('documentPreview').addEventListener('click', (event) => {
  if (event.target.closest('#closePreview')) {
    selectedPreviewId = null;
    renderPreview(null);
  }
  const downloadButton = event.target.closest('[data-download-id]');
  if (downloadButton) downloadDocx(getDocuments().find((item) => item.id === downloadButton.dataset.downloadId));
});

byId('routeTarget').addEventListener('change', renderRouteOptions);
byId('routeForm').addEventListener('submit', (event) => {
  event.preventDefault();
  const destination = byId('routeDestinations').querySelector('input[name="routeDestination"]:checked')?.value;
  if (!destination) return window.alert('Choose a route destination first.');
  const target = byId('routeTarget').value;
  const actionRequired = byId('actionRequired').value;
  if (routeNeedsDetails && !actionRequired) return window.alert('Choose an action required.');
  const documents = getDocuments();
  let changed = 0;
  documents.forEach((item) => {
    if (!routingIds.includes(item.id)) return;
    item.status = target;
    item.to_unit = destination;
    if (routeNeedsDetails) {
      item.remarks = byId('routeRemarks').value.trim();
      item.action_requested = actionRequired;
    }
    changed += 1;
  });
  writeStore(DOCUMENTS_KEY, documents);
  byId('routeDialog').close();
  selectedIds.clear();
  currentView = target;
  render();
  setMessage(`${changed} document(s) routed to ${destination}.`);
});
document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => byId('routeDialog').close()));
byId('routeDialog').addEventListener('click', (event) => {
  if (event.target === byId('routeDialog')) byId('routeDialog').close();
});
