/* ══════════════════════════════════════════════════════════════
   ConnectAfriq - Application JavaScript avec Backend PHP/MySQL
   ══════════════════════════════════════════════════════════════ */

/* ── Configuration API ── */
const API_BASE = 'api';
let authToken = localStorage.getItem('authToken') || null;
let currentUser = JSON.parse(localStorage.getItem('currentUser') || 'null');
let currentUserType = localStorage.getItem('userType') || null;

/* ── Fonctions API ── */
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (authToken) {
        options.headers['Authorization'] = `Bearer ${authToken}`;
    }
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(`${API_BASE}/${endpoint}`, options);
        const result = await response.json();
        
        if (!result.success && response.status === 401) {
            // Session expirée
            logout();
            showToast('Session expirée', 'Veuillez vous reconnecter.');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Erreur de connexion au serveur' };
    }
}

/* ── Initialisation de l'authentification ── */
function initAuth() {
    if (authToken && currentUser) {
        document.getElementById('nav-dashboard').style.display = 'inline';
        document.getElementById('mobile-dashboard').style.display = 'block';
        document.getElementById('page-dashboard').style.display = 'block';
        userProfile = currentUser;
        userType = currentUserType;
        userPoints = currentUser.points || 0;
        userBadges = currentUser.badges || [];
    }
}

/* ── Companies Data (fallback si API indisponible) ── */
const companiesFallback = [
  { name:"Wavecom Sénégal", avatar:"WS", bg:"linear-gradient(135deg,#00e5a0,#00c9ff)", sector:"telecom", city:"Dakar", desc:"Leader en solutions télécommunications, nous recherchons des stagiaires passionnés par la technologie mobile.", tags:["Stage 3 mois","Télécoms","Dakar"], type:"Stage", keywords:["telecom","mobile","tech"] },
  { name:"BanqUe Nationale", avatar:"BN", bg:"linear-gradient(135deg,#f0c040,#ff9f43)", sector:"finance", city:"Dakar", desc:"Banque historique du pays, nous offrons des opportunités dans la finance, le crédit et la gestion portefeuille.", tags:["Emploi","Finance","Dakar"], type:"Emploi", keywords:["finance","banque","gestion"] },
  { name:"TechAfriq Solutions", avatar:"TA", bg:"linear-gradient(135deg,#a855f7,#6366f1)", sector:"tech", city:"Dakar", desc:"Startup tech spécialisée en IA et développement web. Stage idéal pour les passionnés de code.", tags:["Stage 6 mois","Dev Web","IA"], type:"Stage", keywords:["tech","ia","dev"] },
  { name:"Santé Plus Clinique", avatar:"SP", bg:"linear-gradient(135deg,#ef4444,#f97316)", sector:"sante", city:"Saint-Louis", desc:"Établissement de santé multi-spécialités cherchant des stagiaires en médecine et paramedical.", tags:["Stage","Santé","Saint-Louis"], type:"Stage", keywords:["sante","medecine","paramedical"] },
  { name:"EduConnect Afrique", avatar:"EA", bg:"linear-gradient(135deg,#10b981,#14b8a6)", sector:"education", city:"Thiès", desc:"Organisation dédiée à l'éducation numérique, nous créons des contenus pédagogiques innovants.", tags:["Emploi","Éducation","Thiès"], type:"Emploi", keywords:["education","numerique","pedagogie"] },
  { name:"ShopdakaR", avatar:"SD", bg:"linear-gradient(135deg,#f43f5e,#ec4899)", sector:"commerce", city:"Dakar", desc:"Plus grande plateforme e-commerce au Sénégal. Rejoignez notre équipe dynamique marketing et logistique.", tags:["Stage 4 mois","E-commerce","Marketing"], type:"Stage", keywords:["commerce","marketing","logistique"] },
  { name:"Orange Sénégal", avatar:"OS", bg:"linear-gradient(135deg,#f97316,#fb923c)", sector:"telecom", city:"Dakar", desc:"Opérateur télécoms n°1, nous cherchons des talents en réseau, cybersécurité et gestion client.", tags:["Emploi","Télécoms","Réseau"], type:"Emploi", keywords:["telecom","reseau","cyber"] },
  { name:"Groupe Fintech DA", avatar:"GF", bg:"linear-gradient(135deg,#0ea5e9,#38bdf8)", sector:"finance", city:"Dakar", desc:"Fintech innovante spécialisée en paiement mobile et crédit digital pour l'Afrique de l'Ouest.", tags:["Stage 3 mois","Fintech","Mobile Pay"], type:"Stage", keywords:["finance","fintech","mobile"] },
  { name:"Académie NumériQ", avatar:"AN", bg:"linear-gradient(135deg,#8b5cf6,#c084fc)", sector:"education", city:"Dakar", desc:"École de formation numérique avec une approche pratique. Parfait pour les jeunes en reconversion.", tags:["Emploi","Formation","Digital"], type:"Emploi", keywords:["education","formation","digital"] },
  { name:"AgriTech Sénégal", avatar:"AS", bg:"linear-gradient(135deg,#22c55e,#4ade80)", sector:"tech", city:"Kaolack", desc:"Startup ag-tech qui révolutionne l'agriculture sénégalaise avec des capteurs IoT et l'IA.", tags:["Stage","AgriTech","IoT"], type:"Stage", keywords:["tech","agritech","iot"] },
  { name:"Distrib Marché Dakar", avatar:"DM", bg:"linear-gradient(135deg,#eab308,#facc15)", sector:"commerce", city:"Dakar", desc:"Réseau de distribution alimentaire et de grande distribution. Opportunités en logistique et supply chain.", tags:["Emploi","Distribution","Logistique"], type:"Emploi", keywords:["commerce","distribution","logistique"] },
  { name:"Expresso Télécom", avatar:"ET", bg:"linear-gradient(135deg,#6366f1,#818cf8)", sector:"telecom", city:"Dakar", desc:"Opérateur internet fixe et mobile, nous cherchons des ingénieurs réseau et des chargés de client.", tags:["Stage 5 mois","Internet","Réseau"], type:"Stage", keywords:["telecom","internet","reseau"] },
];

// Variable companies qui sera remplie par l'API ou le fallback
let companies = [...companiesFallback];

let currentFilter = 'tous';
let userProfile = null;
let userType = null;
let postulations = 0;
let userPoints = 0;
let userBadges = [];

let projects = [
  { title: "Développement App Mobile pour Agri", desc: "Projet pour créer une app aidant les agriculteurs.", members: 3, max: 5 },
  { title: "Campagne Marketing Digital", desc: "Concevez une campagne pour une startup locale.", members: 2, max: 4 },
];

let mentors = [
  { name: "Dr. Samba Ndiaye", role: "Expert en Tech", avatar: "SN", keywords: ["tech","ia"] },
  { name: "Marie Diop", role: "Consultante Finance", avatar: "MD", keywords: ["finance","banque"] },
];

function renderCompanies(list) {
  const grid = document.getElementById('companiesGrid');
  grid.innerHTML = '';
  list.forEach((c,i) => {
    const card = document.createElement('div');
    card.className = 'company-card reveal';
    card.style.transitionDelay = (i*0.06)+'s';
    card.innerHTML = `
      <div class="company-head">
        <div class="company-avatar" style="background:${c.bg};color:#fff;">${c.avatar}</div>
        <div><h3>${c.name}</h3><span>${c.type} • ${c.city}</span></div>
      </div>
      <div class="company-tags">${c.tags.map((t,j) => `<span class="tag ${j===1?'blue':j===2?'orange':''}">${t}</span>`).join('')}</div>
      <p>${c.desc}</p>
      <div class="company-card-footer">
        <span>📍 ${c.city}</span>
        <button class="btn-apply" onclick="event.stopPropagation();applyToCompany('${c.name}')">Postuler →</button>
      </div>`;
    grid.appendChild(card);
    setTimeout(()=>card.classList.add('visible'), 80+i*60);
  });
}

function filterCompanies() {
  const q = document.getElementById('searchEntreprise').value.toLowerCase();
  let list = companies;
  if(currentFilter !== 'tous') list = list.filter(c => c.sector === currentFilter);
  if(q) list = list.filter(c => c.name.toLowerCase().includes(q) || c.desc.toLowerCase().includes(q) || c.tags.some(t=>t.toLowerCase().includes(q)));
  renderCompanies(list);
}

function setFilter(btn, val) {
  document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  currentFilter = val;
  filterCompanies();
}

async function applyToCompany(name, entrepriseId = null) {
  if (!authToken) {
    showToast('Connexion requise', 'Veuillez vous inscrire ou vous connecter pour postuler.');
    goPage('inscription');
    return;
  }
  
  // Trouver l'ID de l'entreprise si non fourni
  if (!entrepriseId) {
    const company = companies.find(c => c.name === name);
    entrepriseId = company ? company.id : null;
  }
  
  if (entrepriseId) {
    const result = await apiCall('candidatures.php?action=apply', 'POST', { entreprise_id: entrepriseId });
    
    if (result.success) {
      postulations++;
      if (result.data.points) userPoints = result.data.points;
      if (result.data.badges) userBadges = result.data.badges;
      showToast('Demande envoyée !', `Votre candidature pour ${name} a été reçue.`);
      if (result.data.new_badges && result.data.new_badges.length > 0) {
        setTimeout(() => showToast('Nouveau badge !', `Vous avez débloqué : ${result.data.new_badges.join(', ')}`), 2000);
      }
    } else {
      showToast('Info', result.message || 'Candidature déjà envoyée.');
    }
  } else {
    // Fallback mode sans API
    postulations++;
    showToast('Demande envoyée !', `Votre candidature pour ${name} a été reçue. Vous serez contacté bientôt.`);
    earnPoints(20);
  }
  
  if(userProfile) updateDashboard();
}

/* ── AI Recommendations ── */
function renderRecommendations() {
  if(!userProfile || userType !== 'jeune') return;
  const recList = document.getElementById('rec-list');
  recList.innerHTML = '';
  const userKeywords = userProfile.competences.toLowerCase().split(',').map(k=>k.trim());
  const matches = companies.filter(c => userKeywords.some(k => c.keywords.includes(k))).slice(0,3);
  if(matches.length > 0) {
    document.getElementById('ai-recs').style.display = 'block';
    matches.forEach(c => {
      const item = document.createElement('div');
      item.innerHTML = `<strong>${c.name}</strong> - Match AI basé sur vos compétences.`;
      recList.appendChild(item);
    });
  }
}

/* ── Page Navigation ── */
function goPage(id) {
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.getElementById('page-'+id).classList.add('active');
  document.querySelectorAll('.nav-links a').forEach(a=>{a.classList.remove('active'); if(a.dataset.page===id) a.classList.add('active');});
  window.scrollTo({top:0,behavior:'smooth'});
  if(id==='entreprises') { renderCompanies(companies); renderRecommendations(); setTimeout(triggerReveal,100); }
  else if(id==='dashboard') { updateDashboard(); setTimeout(triggerReveal,100); }
  else if(id==='projects') { renderProjects(); setTimeout(triggerReveal,100); }
  else if(id==='mentorship') { renderMentors(); setTimeout(triggerReveal,100); }
  else setTimeout(triggerReveal,100);
}

/* ── Scroll Reveal ── */
function triggerReveal() {
  document.querySelectorAll('.reveal').forEach(el => {
    const rect = el.getBoundingClientRect();
    if(rect.top < window.innerHeight - 60) el.classList.add('visible');
  });
}
window.addEventListener('scroll', triggerReveal);
triggerReveal();

/* ── Counter animation ── */
function animateCounters() {
  document.querySelectorAll('.stat-number').forEach(el => {
    const target = +el.dataset.target;
    const duration = 1800;
    const start = performance.now();
    function update(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(ease * target);
      if(progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
  });
}

// trigger counter when stats visible
const statsObs = new IntersectionObserver(entries => { entries.forEach(e => { if(e.isIntersecting) { animateCounters(); statsObs.unobserve(e.target); } }); }, {threshold:.3});
document.querySelector('.stats-strip') && statsObs.observe(document.querySelector('.stats-strip'));

/* ── Tabs ── */
function switchTab(name, btn) {
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
}

/* ── Form submit avec API ── */
async function submitForm(type) {
  if(type==='jeune') {
    const data = {
      prenom: document.getElementById('jeune-prenom').value,
      nom: document.getElementById('jeune-nom').value,
      email: document.getElementById('jeune-email').value,
      password: document.getElementById('jeune-pwd').value,
      telephone: document.getElementById('jeune-tel').value,
      ville: document.getElementById('jeune-ville').value,
      region: document.getElementById('jeune-region').value,
      diplome: document.getElementById('jeune-diplome').value,
      domaine: document.getElementById('jeune-domaine').value,
      type_opportunite: document.getElementById('jeune-type').value,
      competences: document.getElementById('jeune-competences').value
    };
    
    // Validation basique
    if (!data.prenom || !data.nom || !data.email || !data.password) {
      showToast('Erreur', 'Veuillez remplir tous les champs obligatoires.');
      return;
    }
    
    if (data.password.length < 8) {
      showToast('Erreur', 'Le mot de passe doit contenir au moins 8 caractères.');
      return;
    }
    
    const result = await apiCall('auth.php?action=register', 'POST', data);
    
    if (result.success) {
      authToken = result.data.token;
      currentUser = result.data.user;
      currentUserType = 'jeune';
      userProfile = result.data.user;
      userType = 'jeune';
      userPoints = result.data.user.points || 100;
      userBadges = result.data.user.badges || [];
      
      // Sauvegarder dans localStorage
      localStorage.setItem('authToken', authToken);
      localStorage.setItem('currentUser', JSON.stringify(currentUser));
      localStorage.setItem('userType', 'jeune');
      
      showToast('Compte créé avec succès !', `Bienvenue sur ConnectAfriq, ${userProfile.prenom} !`);
    } else {
      showToast('Erreur', result.message || 'Erreur lors de la création du compte.');
      return;
    }
    
  } else {
    const data = {
      nom: document.getElementById('ent-nom').value,
      contact_nom: document.getElementById('ent-contact').value,
      contact_poste: document.getElementById('ent-poste').value,
      email: document.getElementById('ent-email').value,
      password: document.getElementById('ent-pwd').value,
      telephone: document.getElementById('ent-tel').value,
      secteur: document.getElementById('ent-secteur').value,
      ville: document.getElementById('ent-ville').value,
      description: document.getElementById('ent-desc').value
    };
    
    // Validation basique
    if (!data.nom || !data.email || !data.password) {
      showToast('Erreur', 'Veuillez remplir tous les champs obligatoires.');
      return;
    }
    
    const result = await apiCall('auth.php?action=register-entreprise', 'POST', data);
    
    if (result.success) {
      authToken = result.data.token;
      currentUser = result.data.entreprise;
      currentUserType = 'entreprise';
      userProfile = result.data.entreprise;
      userType = 'entreprise';
      
      // Sauvegarder dans localStorage
      localStorage.setItem('authToken', authToken);
      localStorage.setItem('currentUser', JSON.stringify(currentUser));
      localStorage.setItem('userType', 'entreprise');
      
      showToast('Entreprise enregistrée !', 'Votre entreprise est bien enregistrée.');
    } else {
      showToast('Erreur', result.message || 'Erreur lors de l\'enregistrement.');
      return;
    }
  }
  
  document.getElementById('nav-dashboard').style.display = 'inline';
  document.getElementById('mobile-dashboard').style.display = 'block';
  document.getElementById('page-dashboard').style.display = 'block';
  goPage('dashboard');
}

/* ── Dashboard Update ── */
function updateDashboard() {
  if(!userProfile) return;
  document.getElementById('dash-name').textContent = userType === 'jeune' ? userProfile.prenom : userProfile.nom;
  // Progress mock
  const progress = Object.values(userProfile).filter(v => v).length / Object.keys(userProfile).length * 100;
  document.getElementById('progress-fill').style.width = `${progress}%`;
  // Notifications mock
  const notifs = document.getElementById('notifications');
  notifs.innerHTML = '';
  ['Nouvelle offre en Tech !', 'Votre CV a été vu par 3 entreprises.', 'Atelier AI demain.'].forEach(n => {
    const div = document.createElement('div');
    div.className = 'notification';
    div.innerHTML = `<span class="notification-icon">🔔</span><span class="notification-text">${n}</span>`;
    notifs.appendChild(div);
  });
  // Matching AI mock
  const matching = document.getElementById('matching-list');
  matching.innerHTML = '';
  const mockMatches = companies.slice(0,3).map(c => c.name);
  mockMatches.forEach(m => {
    const div = document.createElement('div');
    div.innerHTML = `<strong>${m}</strong> - Score AI: 85%`;
    matching.appendChild(div);
  });
  // Stats
  document.getElementById('dash-apps').textContent = postulations;
  document.getElementById('dash-offers').textContent = Math.floor(Math.random() * 5);
  // Gamification
  document.getElementById('user-points').textContent = userPoints;
  const badgeList = document.getElementById('badge-list');
  badgeList.innerHTML = '';
  ['🏆', '⭐', '🔥', '💎'].forEach((b, i) => {
    const badgeEl = document.createElement('div');
    badgeEl.className = 'badge' + (userBadges.includes(i) ? ' unlocked' : '');
    badgeEl.textContent = b;
    badgeList.appendChild(badgeEl);
  });
  // Leaderboard mock
  const leaderboard = document.getElementById('leaderboard-list');
  leaderboard.innerHTML = '';
  ['Aminata: 500 pts', 'Moussa: 450 pts', 'Fatou: 400 pts'].forEach(l => {
    const item = document.createElement('div');
    item.className = 'leaderboard-item';
    item.innerHTML = l;
    leaderboard.appendChild(item);
  });
}

/* ── Logout ── */
async function logout() {
  // Appeler l'API de déconnexion
  if (authToken) {
    await apiCall('auth.php?action=logout', 'POST');
  }
  
  // Nettoyer les variables
  userProfile = null;
  userType = null;
  postulations = 0;
  userPoints = 0;
  userBadges = [];
  authToken = null;
  currentUser = null;
  currentUserType = null;
  
  // Nettoyer le localStorage
  localStorage.removeItem('authToken');
  localStorage.removeItem('currentUser');
  localStorage.removeItem('userType');
  
  document.getElementById('nav-dashboard').style.display = 'none';
  document.getElementById('mobile-dashboard').style.display = 'none';
  document.getElementById('page-dashboard').style.display = 'none';
  document.getElementById('ai-recs').style.display = 'none';
  goPage('home');
  showToast('Déconnexion', 'Vous avez été déconnecté avec succès.');
}

/* ── CV Generator (mock AI) ── */
function generateCV() {
  const input = document.getElementById('cv-input').value;
  const output = document.getElementById('cv-output');
  // Mock AI: Ajoute des optimisations simples
  let optimized = input.replace(/cv/gi, 'CV Optimisé AI').toUpperCase();
  optimized += '\n\nAjouts AI: + Mots-clés SEO pour recruteurs, + Structure professionnelle.';
  output.textContent = optimized;
  output.style.display = 'block';
  showToast('CV Généré !', 'Votre CV a été optimisé par notre AI.');
  earnPoints(30);
}

/* ── Toast ── */
function showToast(title, msg) {
  document.getElementById('toastTitle').textContent = title;
  document.getElementById('toastMsg').textContent = msg;
  const toast = document.getElementById('toast');
  toast.classList.add('show');
  setTimeout(()=>toast.classList.remove('show'), 3800);
}

/* ── FAB toggle ── */
let fabOpen = true;
function toggleFab() {
  fabOpen = !fabOpen;
  document.getElementById('fabIcon').textContent = fabOpen ? '+' : '✕';
  document.querySelectorAll('.fab-mini').forEach((m,i) => {
    setTimeout(()=>m.classList.toggle('visible',fabOpen), i*60);
  });
}

/* ── Scroll to top ── */
function scrollTop() { window.scrollTo({top:0,behavior:'smooth'}); }

/* ── Mobile nav ── */
function toggleMobile() { document.getElementById('mobileNav').classList.toggle('open'); }
function closeMobile() { document.getElementById('mobileNav').classList.remove('open'); }

/* ── Chat Support ── */
let chatOpen = false;
function toggleChat() {
  chatOpen = !chatOpen;
  document.getElementById('chatWidget').style.display = chatOpen ? 'block' : 'none';
}

function sendMessage() {
  const input = document.getElementById('chatInput');
  const msg = input.value.trim();
  if(!msg) return;
  const messages = document.getElementById('chatMessages');
  const userMsg = document.createElement('div');
  userMsg.className = 'message message-user';
  userMsg.textContent = msg;
  messages.appendChild(userMsg);
  input.value = '';
  // Mock AI response
  setTimeout(() => {
    const botMsg = document.createElement('div');
    botMsg.className = 'message message-bot';
    botMsg.textContent = getBotResponse(msg);
    messages.appendChild(botMsg);
    messages.scrollTop = messages.scrollHeight;
  }, 1000);
}

function getBotResponse(msg) {
  const lower = msg.toLowerCase();
  if(lower.includes('postuler')) return 'Pour postuler, allez sur la page Entreprises et cliquez sur "Postuler →" sur une offre.';
  if(lower.includes('cv')) return 'Utilisez notre générateur de CV AI dans la section Inscription pour optimiser votre CV.';
  if(lower.includes('aide')) return 'Je suis là pour vous aider ! Posez-moi une question spécifique.';
  return 'Désolé, je n\'ai pas compris. Pouvez-vous reformuler ?';
}

/* ─── Nouvelle Fonctionnalité 1: Gamification ─── */
function earnPoints(points) {
  if(!userProfile) return;
  userPoints += points;
  showToast('Points Gagnés !', `Vous avez gagné ${points} points !`);
  // Unlock badges based on points
  if(userPoints >= 100 && !userBadges.includes(0)) userBadges.push(0);
  if(userPoints >= 200 && !userBadges.includes(1)) userBadges.push(1);
  if(userPoints >= 300 && !userBadges.includes(2)) userBadges.push(2);
  if(userPoints >= 500 && !userBadges.includes(3)) userBadges.push(3);
  updateDashboard();
}

/* ─── Nouvelle Fonctionnalité 2: Mentorship Matching ─── */
function renderMentors() {
  const grid = document.getElementById('mentorsGrid');
  grid.innerHTML = '';
  let filtered = mentors;
  if(userProfile && userType === 'jeune') {
    const userKeywords = userProfile.competences.toLowerCase().split(',').map(k=>k.trim());
    filtered = mentors.filter(m => userKeywords.some(k => m.keywords.includes(k)));
  }
  filtered.forEach(m => {
    const card = document.createElement('div');
    card.className = 'mentor-card';
    card.innerHTML = `
      <div class="mentor-avatar">${m.avatar}</div>
      <div class="mentor-name">${m.name}</div>
      <div class="mentor-role">${m.role}</div>
      <button class="mentor-btn" onclick="requestMentor('${m.name}')">Demander Mentorat</button>
    `;
    grid.appendChild(card);
  });
}

function requestMentor(name) {
  showToast('Demande Envoyée !', `Votre demande de mentorat à ${name} a été envoyée.`);
  earnPoints(50);
}

/* ─── Nouvelle Fonctionnalité 3: Career Simulator ─── */
function simulateCareer() {
  const skill = document.getElementById('sim-skill').value.trim();
  if(!skill) return;
  const result = document.getElementById('sim-result');
  result.innerHTML = '';
  // Mock simulation
  const paths = [
    { year: 1, job: 'Stagiaire', salary: '100K CFA' },
    { year: 3, job: 'Junior', salary: '300K CFA' },
    { year: 5, job: 'Senior', salary: '500K CFA' }
  ];
  paths.forEach(p => {
    const step = document.createElement('div');
    step.className = 'path-step';
    step.innerHTML = `Année ${p.year}: ${p.job} avec compétence "${skill}" - Salaire estimés: ${p.salary}`;
    result.appendChild(step);
  });
  showToast('Simulation Complète !', 'Découvrez votre chemin de carrière potentiel.');
  earnPoints(40);
}

/* ─── Nouvelle Fonctionnalité 4: Collaborative Projects ─── */
function renderProjects() {
  const grid = document.getElementById('projectsGrid');
  grid.innerHTML = '';
  projects.forEach((p, i) => {
    const card = document.createElement('div');
    card.className = 'project-card';
    card.innerHTML = `
      <div class="project-title">${p.title}</div>
      <p class="project-desc">${p.desc}</p>
      <div class="project-members">Membres: ${p.members}/${p.max}</div>
      <button class="project-btn" onclick="joinProject(${i})">Rejoindre</button>
    `;
    grid.appendChild(card);
  });
}

function joinProject(index) {
  if(projects[index].members < projects[index].max) {
    projects[index].members++;
    showToast('Rejoint !', `Vous avez rejoint le projet "${projects[index].title}".`);
    earnPoints(80);
    renderProjects();
  } else {
    showToast('Plein !', 'Ce projet est complet.');
  }
}

function createProject() {
  const title = prompt('Titre du projet :');
  const desc = prompt('Description :');
  if(title && desc) {
    projects.push({ title, desc, members: 1, max: 5 });
    showToast('Projet Créé !', `Votre projet "${title}" est maintenant disponible.`);
    earnPoints(100);
    renderProjects();
  }
}

/* ── Charger les entreprises depuis l'API ── */
async function loadCompaniesFromAPI() {
  const result = await apiCall('entreprises.php?action=list');
  if (result.success && result.data && result.data.length > 0) {
    companies = result.data.map(e => ({
      id: e.id,
      name: e.nom,
      avatar: e.avatar,
      bg: e.bg,
      sector: e.secteur,
      city: e.ville,
      desc: e.description,
      tags: e.tags || [],
      type: e.type || 'Emploi',
      keywords: [e.secteur]
    }));
  }
  renderCompanies(companies);
}

/* ── Charger les projets depuis l'API ── */
async function loadProjectsFromAPI() {
  const result = await apiCall('projets.php?action=list');
  if (result.success && result.data) {
    projects = result.data.map(p => ({
      id: p.id,
      title: p.titre,
      desc: p.description,
      members: p.membres_actuels,
      max: p.membres_max
    }));
  }
  renderProjects();
}

/* ── Charger les mentors depuis l'API ── */
async function loadMentorsFromAPI() {
  const result = await apiCall('mentors.php?action=list');
  if (result.success && result.data) {
    mentors = result.data.map(m => ({
      id: m.id,
      name: m.nom,
      role: m.role,
      avatar: m.avatar,
      keywords: m.keywords || []
    }));
  }
  renderMentors();
}

/* ── Charger le leaderboard depuis l'API ── */
async function loadLeaderboard() {
  const result = await apiCall('gamification.php?action=leaderboard&limit=5');
  if (result.success && result.data) {
    const leaderboard = document.getElementById('leaderboard-list');
    if (leaderboard) {
      leaderboard.innerHTML = '';
      result.data.forEach(u => {
        const item = document.createElement('div');
        item.className = 'leaderboard-item';
        item.innerHTML = `${u.prenom} ${u.nom.charAt(0)}.: ${u.points} pts`;
        leaderboard.appendChild(item);
      });
    }
  }
}

/* ── Initial ── */
async function initApp() {
  // Initialiser l'authentification
  initAuth();
  
  // Charger les données depuis l'API (avec fallback)
  await loadCompaniesFromAPI();
  await loadProjectsFromAPI();
  await loadMentorsFromAPI();
  
  triggerReveal();
}

// Lancer l'application
initApp();
