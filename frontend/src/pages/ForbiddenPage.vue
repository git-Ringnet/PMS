<template>
  <div class="forbidden-page">
    <div class="forbidden-card">
      <div class="icon">🔒</div>
      <h1>403</h1>
      <h2>Không có quyền truy cập</h2>
      <p>Bạn không có quyền truy cập trang này tại chi nhánh hiện tại.</p>
      <div class="actions">
        <button class="btn-primary" @click="goHome">Về trang chủ</button>
        <button class="btn-secondary" @click="goBack">Quay lại</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const goHome = () => router.push('/')
const goBack = () => router.back()
</script>

<style scoped>
.forbidden-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
}

.forbidden-card {
  text-align: center;
  padding: 3rem 4rem;
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 1.5rem;
  color: #fff;
  max-width: 480px;
}

.icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

h1 {
  font-size: 5rem;
  font-weight: 800;
  color: #e94560;
  margin: 0;
  line-height: 1;
}

h2 {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0.75rem 0;
  color: #fff;
}

p {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.95rem;
  margin-bottom: 2rem;
}

.actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
}

.btn-primary {
  padding: 0.75rem 1.5rem;
  background: #e94560;
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 600;
  transition: opacity 0.2s;
}

.btn-primary:hover { opacity: 0.85; }

.btn-secondary {
  padding: 0.75rem 1.5rem;
  background: transparent;
  color: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}
</style>
