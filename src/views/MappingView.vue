<script setup>
import { ref, reactive, onUnmounted, nextTick, watch } from 'vue'
import { useTinnitusStore } from '../stores/tinnitusStore'
import { useTinnitusAudio } from '../composables/useTinnitusAudio'
import Slider from 'primevue/slider'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { 
  Waves, 
  Volume2, 
  Settings2, 
  Activity, 
  Zap, 
  ArrowLeft,
  Ear,
  CloudLightning,
  CheckCircle2
} from 'lucide-vue-next'

const store = useTinnitusStore()
const audio = useTinnitusAudio()

// State
const initialized = ref(false)
const evaluationScope = ref('ambos') // 'left', 'right', 'ambos'
const activeEar = ref('left')
const showConfirmDialog = ref(false)
const tempScope = ref('ambos')

const defaultLayers = () => [
  { id: 'tono_pulsatil', name: 'Tono Pulsátil', desc: 'Sincronizado o rítmico', type: 'pulse', freq: 50, vol: 0, speed: 50, color: '#10B981' },
  { id: 'ruido_banda', name: 'Ruido de Banda', desc: 'Multiespectral continuo', type: 'noise', freq: 40, vol: 0, speed: null, color: '#3B82F6' },
  { id: 'tono_puro', name: 'Tono Puro', desc: 'Monofrecuencial estable', type: 'pure', freq: 55, vol: 0, speed: null, color: '#8B5CF6' },
  { id: 'tono_modulado', name: 'Tono Modulado', desc: 'Pulsación de frecuencia', type: 'sweep', freq: 62, vol: 0, speed: 30, color: '#F59E0B' }
]

const leftLayers = ref(defaultLayers())
const rightLayers = ref(defaultLayers())

// Animation Frames
const waveAnimFrames = {}

const handleScopeSelection = (scope) => {
  tempScope.value = scope
  showConfirmDialog.value = true
}

const startMapping = () => {
  evaluationScope.value = tempScope.value
  activeEar.value = tempScope.value === 'right' ? 'right' : 'left'
  initialized.value = true
  showConfirmDialog.value = false
  audio.initContext()
}

const resetMapper = () => {
  // Stop all sounds before going back
  ['left', 'right'].forEach(ear => {
    ['tono_pulsatil', 'ruido_banda', 'tono_puro', 'tono_modulado'].forEach(id => {
      audio.stopLayer(ear, id)
    })
  })
  initialized.value = false
}

const toggleLayer = (layerId) => {
  const ear = activeEar.value
  const nodes = audio.activeNodes[ear]
  
  if (nodes[layerId]) {
    audio.stopLayer(ear, layerId)
  } else {
    const layers = ear === 'left' ? leftLayers.value : rightLayers.value
    const layer = layers.find(l => l.id === layerId)
    audio.startLayer(ear, layer)
    // Inicializar animación una vez que el canvas existe
    nextTick(() => {
      startWaveAnim(ear, layerId)
    })
  }
}

const getLayer = (ear, id) => {
  const list = ear === 'left' ? leftLayers.value : rightLayers.value
  return list.find(l => l.id === id)
}

const updateFreq = (id, val) => {
  audio.updateFreq(activeEar.value, id, val)
}

const updateVol = (id, val) => {
  audio.updateVol(activeEar.value, id, val)
}

const updateSpeed = (id, val) => {
  audio.updateSpeed(activeEar.value, id, val)
}

const fmtFreq = (f) => {
  const hz = audio.freqFromSlider(f)
  return hz >= 1000 ? (hz / 1000).toFixed(1) + ' kHz' : hz + ' Hz'
}

const fmtSpeed = (v) => {
  return audio.speedFromSlider(v) + ' Hz'
}

const handleVolSlideEnd = (layer) => {
  if (!audio.activeNodes[activeEar.value][layer.id] && layer.vol > 0) {
    toggleLayer(layer.id)
  }
}

// Visualizer Logic
const startWaveAnim = (ear, layerId) => {
  const canvasId = `wave-${ear}-${layerId}`
  const canvas = document.getElementById(canvasId)
  if (!canvas) return

  const ctx2d = canvas.getContext('2d')
  const layer = getLayer(ear, layerId)
  
  const draw = () => {
    if (!audio.activeNodes[ear][layerId]) return
    
    const W = canvas.width = canvas.offsetWidth
    const H = canvas.height = canvas.offsetHeight
    const freq = audio.freqFromSlider(layer.freq)
    const vol = layer.vol / 100
    const spd = layer.speed !== null ? audio.speedFromSlider(layer.speed) : 1
    const color = layer.color
    
    ctx2d.clearRect(0, 0, W, H)
    
    const time = performance.now() / 1000
    const amp = (H / 2 - 4) * vol
    
    ctx2d.strokeStyle = color
    ctx2d.lineWidth = 3
    ctx2d.lineJoin = 'round'
    ctx2d.beginPath()
    
    if (layer.type === 'pure' || layer.type === 'pulse' || layer.type === 'sweep') {
      const cycles = 2 + (freq / 1000)
      const pulse = layer.type === 'pulse' ? Math.pow(Math.sin(time * spd * Math.PI), 2) : 1
      
      for (let x = 0; x <= W; x++) {
        const nx = x / W
        const angle = nx * cycles * Math.PI * 2 - time * 10
        const y = H / 2 + Math.sin(angle) * amp * (layer.type === 'pulse' ? (0.2 + pulse * 0.8) : 1)
        x === 0 ? ctx2d.moveTo(x, y) : ctx2d.lineTo(x, y)
      }
    } else {
      for (let x = 0; x <= W; x++) {
        const n = (Math.random() - 0.5) * 2
        const y = H / 2 + n * amp
        x === 0 ? ctx2d.moveTo(x, y) : ctx2d.lineTo(x, y)
      }
    }
    
    ctx2d.stroke()
    waveAnimFrames[canvasId] = requestAnimationFrame(draw)
  }
  
  draw()
}

onUnmounted(() => {
  ['left', 'right'].forEach(ear => {
    ['tono_pulsatil', 'ruido_banda', 'tono_puro', 'tono_modulado'].forEach(id => {
      audio.stopLayer(ear, id)
    })
  })
  Object.values(waveAnimFrames).forEach(id => cancelAnimationFrame(id))
})

const saveMapping = () => {
  alert('Mapeo de Tinnitus guardado correctamente en la ficha del paciente.')
}
</script>

<template>
  <div class="h-[calc(100vh-160px)] min-h-[600px] flex flex-col gap-6 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between shrink-0">
      <div class="flex items-center gap-4">
        <Button 
          v-if="initialized" 
          @click="resetMapper" 
          icon="pi pi-arrow-left" 
          text 
          rounded 
          class="text-primary-400 hover:bg-primary-50" 
        />
        <div>
          <h1 class="text-xl font-black text-primary-900 flex items-center gap-3">
            <Waves class="text-accent-blue" :size="24" />
            Mapeador de Tinnitus
          </h1>
          <p class="text-xs text-primary-500 mt-1">
            Síntesis de frecuencia y calibración del gemelo digital.
          </p>
        </div>
      </div>
    </div>

    <!-- Init Screen: Scope Selector -->
    <div v-if="!initialized" class="flex-1 flex flex-col items-center justify-center text-center p-12 bg-white rounded-3xl border border-dashed border-primary-200">
      <div class="size-20 bg-primary-50 rounded-full flex items-center justify-center mb-8 border border-primary-100">
        <Ear class="text-primary-300" :size="40" />
      </div>
      <h2 class="text-2xl font-black text-primary-900 mb-2">Localización del Acúfeno</h2>
      <p class="text-sm text-primary-500 mb-12 max-w-md mx-auto">
        Inicie la síntesis de frecuencia seleccionando dónde percibe el sonido de forma predominante.
      </p>
      
      <div class="flex flex-col md:flex-row gap-4 w-full max-w-2xl">
        <button 
          @click="handleScopeSelection('left')"
          class="flex-1 p-8 bg-white border-2 border-primary-100 rounded-3xl hover:border-audio-left hover:bg-audio-left/5 transition-all group text-left"
        >
          <div class="size-12 rounded-2xl bg-audio-left/10 flex items-center justify-center mb-4 group-hover:bg-audio-left group-hover:text-white transition-colors duration-500">
            <Volume2 :size="24" />
          </div>
          <p class="text-[10px] font-black uppercase tracking-widest text-audio-left mb-1">Lateralizado</p>
          <p class="text-lg font-black text-primary-900">Oído Izquierdo</p>
        </button>

        <button 
          @click="handleScopeSelection('right')"
          class="flex-1 p-8 bg-white border-2 border-primary-100 rounded-3xl hover:border-audio-right hover:bg-audio-right/5 transition-all group text-left"
        >
          <div class="size-12 rounded-2xl bg-audio-right/10 flex items-center justify-center mb-4 group-hover:bg-audio-right group-hover:text-white transition-colors duration-500">
            <Volume2 :size="24" />
          </div>
          <p class="text-[10px] font-black uppercase tracking-widest text-audio-right mb-1">Lateralizado</p>
          <p class="text-lg font-black text-primary-900">Oído Derecho</p>
        </button>

        <button 
          @click="handleScopeSelection('ambos')"
          class="flex-1 p-8 bg-primary-900 border-2 border-primary-900 rounded-3xl hover:bg-primary-800 transition-all text-left"
        >
          <div class="size-12 rounded-2xl bg-white/10 flex items-center justify-center mb-4 text-emerald-400">
            <Activity :size="24" />
          </div>
          <p class="text-[10px] font-black uppercase tracking-widest text-emerald-400/60 mb-1">Global</p>
          <p class="text-lg font-black text-white">Bilateral</p>
        </button>
      </div>
    </div>

    <!-- Active Mapper Panel -->
    <div v-else class="flex-1 flex flex-col gap-6 min-h-0">
      <!-- Ear Selector (Only if Bilingual) -->
      <div v-if="evaluationScope === 'ambos'" class="flex justify-center shrink-0">
        <div class="flex p-1 bg-primary-900 rounded-2xl shadow-xl border border-white/5 w-full max-w-sm gap-1">
          <button 
            @click="activeEar = 'left'"
            :class="[
              'flex-1 py-3 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border-2', 
              activeEar === 'left' ? 'bg-audio-left border-audio-left text-white shadow-lg shadow-blue-500/20' : 'bg-transparent border-transparent text-white/40 hover:text-white/60'
            ]"
          >
            Config. Izquierdo
          </button>
          <button 
            @click="activeEar = 'right'"
            :class="[
              'flex-1 py-3 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border-2', 
              activeEar === 'right' ? 'bg-audio-right border-audio-right text-white shadow-lg shadow-red-500/20' : 'bg-transparent border-transparent text-white/40 hover:text-white/60'
            ]"
          >
            Config. Derecho
          </button>
        </div>
      </div>

      <!-- Synthesizer Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1 min-h-0 overflow-y-auto pr-2 custom-scrollbar">
        <div 
          v-for="layer in (activeEar === 'left' ? leftLayers : rightLayers)" 
          :key="activeEar + '-' + layer.id"
          class="bg-white p-6 rounded-3xl border transition-all duration-300 flex flex-col gap-6"
          :class="audio.activeNodes[activeEar][layer.id] ? 'border-primary-900 shadow-xl' : 'border-primary-100 shadow-sm'"
        >
          <!-- Layer Header & Visualizer -->
          <div class="flex flex-col gap-4">
            <div 
              @click="toggleLayer(layer.id)"
              class="flex items-center gap-4 cursor-pointer group"
            >
              <div 
                class="size-12 rounded-2xl flex items-center justify-center transition-all duration-500"
                :style="{ backgroundColor: audio.activeNodes[activeEar][layer.id] ? layer.color : layer.color + '10', color: audio.activeNodes[activeEar][layer.id] ? 'white' : layer.color }"
              >
                <Zap v-if="layer.type === 'pulse'" :size="20" />
                <Settings2 v-else-if="layer.type === 'noise'" :size="20" />
                <Activity v-else-if="layer.type === 'pure'" :size="20" />
                <Waves v-else :size="20" />
              </div>
              <div class="flex-1 min-w-0">
                <h3 class="text-sm font-black text-primary-900 uppercase tracking-widest truncate">{{ layer.name }}</h3>
                <p class="text-[10px] text-primary-400 uppercase tracking-tighter">{{ layer.desc }}</p>
              </div>
              <div 
                class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase transition-all duration-500"
                :class="audio.activeNodes[activeEar][layer.id] ? 'bg-green-500 text-white' : 'bg-primary-50 text-primary-300'"
              >
                {{ audio.activeNodes[activeEar][layer.id] ? 'Activo' : 'OFF' }}
              </div>
            </div>

            <!-- Kinetic Wave Visualizer -->
            <div 
              class="h-16 w-full rounded-2xl overflow-hidden relative transition-all duration-500"
              :class="audio.activeNodes[activeEar][layer.id] ? 'bg-primary-950 shadow-inner' : 'bg-primary-50/50'"
            >
              <canvas :id="`wave-${activeEar}-${layer.id}`" class="w-full h-full opacity-80"></canvas>
              <div v-if="!audio.activeNodes[activeEar][layer.id]" class="absolute inset-0 flex items-center justify-center">
                <div class="w-1/2 h-[1px] bg-primary-200"></div>
              </div>
            </div>
          </div>

          <!-- Controls Section -->
          <div class="grid grid-cols-1 gap-6 px-1">
            <div class="space-y-3">
              <div class="flex justify-between items-end">
                <label class="text-[10px] font-black text-primary-500 uppercase tracking-widest">Frecuencia</label>
                <span class="text-xs font-black text-primary-900">{{ fmtFreq(layer.freq) }}</span>
              </div>
              <Slider 
                v-model="layer.freq" 
                :min="0" :max="100" 
                class="custom-slider" 
                @change="updateFreq(layer.id, $event)" 
              />
            </div>

            <div class="grid grid-cols-2 gap-6">
              <div class="space-y-3">
                <div class="flex justify-between items-end">
                  <label class="text-[10px] font-black text-primary-500 uppercase tracking-widest">Intensidad</label>
                  <span class="text-xs font-black text-primary-900">{{ layer.vol }}%</span>
                </div>
                <Slider 
                  v-model="layer.vol" 
                  :min="0" :max="100" 
                  class="custom-slider-vol" 
                  @change="updateVol(layer.id, $event)" 
                  @slideend="handleVolSlideEnd(layer)"
                />
              </div>

              <div v-if="layer.speed !== null" class="space-y-3">
                <div class="flex justify-between items-end">
                  <label class="text-[10px] font-black text-primary-500 uppercase tracking-widest">Velocidad</label>
                  <span class="text-xs font-black text-primary-900">{{ fmtSpeed(layer.speed) }}</span>
                </div>
                <Slider 
                  v-model="layer.speed" 
                  :min="0" :max="100" 
                  class="custom-slider-speed" 
                  @change="updateSpeed(layer.id, $event)" 
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Footer -->
      <div class="flex items-center justify-between p-6 bg-primary-900 rounded-3xl shadow-2xl shrink-0">
        <div class="flex items-center gap-4 text-white/60">
          <CloudLightning :size="20" class="text-emerald-400" />
          <div class="text-[10px] uppercase font-bold tracking-widest">
            Sintetizador Activo: <span class="text-white">Gemelo Digital en modo escucha</span>
          </div>
        </div>
        <Button 
          @click="saveMapping" 
          icon="pi pi-check-circle" 
          label="Guardar Mapeo de Tinnitus" 
          class="bg-emerald-500 border-none px-8 py-3 font-black uppercase text-xs tracking-widest shadow-lg shadow-emerald-500/20" 
        />
      </div>
    </div>
  </div>

  <!-- Confirm Scope Selection Modal -->
  <Dialog v-model:visible="showConfirmDialog" modal header="Confirmar Localización" :style="{ width: '400px' }">
    <div class="space-y-6 pt-4 text-center">
      <div class="size-20 mx-auto bg-primary-50 rounded-full flex items-center justify-center border border-primary-100 mb-2">
        <Ear class="text-accent-blue" :size="32" />
      </div>
      
      <div>
        <p class="text-[10px] font-black text-primary-400 uppercase tracking-widest mb-1">Has seleccionado</p>
        <p class="text-xl font-black text-primary-900 uppercase">
          {{ tempScope === 'left' ? 'Oído Izquierdo' : tempScope === 'right' ? 'Oído Derecho' : 'Bilateral / Ambos' }}
        </p>
      </div>

      <p class="text-sm text-primary-500 leading-relaxed px-4">
        ¿Deseas iniciar el mapeo sonoro con esta configuración? Podrás cambiarlo después si es necesario.
      </p>

      <div class="flex gap-3 pt-2">
        <Button label="Cancelar" severity="secondary" text @click="showConfirmDialog = false" class="text-xs uppercase font-bold flex-1" />
        <Button 
          @click="startMapping" 
          label="Confirmar e Iniciar" 
          severity="primary" 
          class="bg-primary-900 border-none text-xs uppercase font-bold flex-1 py-4 shadow-xl" 
        />
      </div>
    </div>
  </Dialog>
</template>

<style>
.custom-slider .p-slider-handle, .custom-slider-vol .p-slider-handle, .custom-slider-speed .p-slider-handle {
  @apply border-none size-3 shadow-lg !important;
}

.custom-slider .p-slider-handle { background-color: #3b82f6 !important; }
.custom-slider .p-slider-range { background-color: rgba(59, 130, 246, 0.2) !important; }

.custom-slider-vol .p-slider-handle { background-color: #10B981 !important; }
.custom-slider-vol .p-slider-range { background-color: rgba(16, 185, 129, 0.2) !important; }

.custom-slider-speed .p-slider-handle { background-color: #F59E0B !important; }
.custom-slider-speed .p-slider-range { background-color: rgba(245, 158, 11, 0.2) !important; }
</style>
