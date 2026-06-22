<template>
  <el-container class="app-container">
    <el-aside width="220px" class="app-aside">
      <div class="logo">
        <el-icon size="24"><Van /></el-icon>
        <span>物流扩展体系</span>
      </div>
      <el-menu
        :default-active="activeMenu"
        router
        background-color="#001529"
        text-color="#c9d1d9"
        active-text-color="#409eff"
      >
        <el-menu-item index="/">
          <el-icon><DataAnalysis /></el-icon>
          <span>数据概览</span>
        </el-menu-item>
        <el-menu-item index="/carriers">
          <el-icon><Van /></el-icon>
          <span>承运商管理</span>
        </el-menu-item>
        <el-menu-item index="/tracking">
          <el-icon><Location /></el-icon>
          <span>轨迹回传</span>
        </el-menu-item>
        <el-menu-item index="/tracking/logs">
          <el-icon><Document /></el-icon>
          <span>回调日志</span>
        </el-menu-item>
        <el-menu-item index="/tracking/rollback">
          <el-icon><RefreshLeft /></el-icon>
          <span>轨迹回滚</span>
        </el-menu-item>
        <el-menu-item index="/extension">
          <el-icon><Setting /></el-icon>
          <span>扩展配置</span>
        </el-menu-item>
        <el-menu-item index="/mapping">
          <el-icon><Connection /></el-icon>
          <span>状态映射</span>
        </el-menu-item>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="app-header">
        <div class="header-title">物流扩展体系配置平台</div>
        <div class="header-right">
          <el-tag type="success">v1.0.0</el-tag>
        </div>
      </el-header>
      <el-main class="app-main">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const activeMenu = computed(() => {
  const p = route.path
  if (p.startsWith('/tracking')) return '/tracking'
  return p
})
</script>

<style>
html, body, #app {
  height: 100%;
  margin: 0;
  padding: 0;
}
</style>

<style scoped>
.app-container {
  height: 100vh;
}
.app-aside {
  background: #001529;
  color: #fff;
}
.logo {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #fff;
  font-size: 16px;
  font-weight: 600;
  border-bottom: 1px solid #1f3652;
}
.el-menu {
  border-right: none;
}
.app-header {
  background: #fff;
  border-bottom: 1px solid #ebeef5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
}
.header-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2d3d;
}
.app-main {
  background: #f5f7fa;
  padding: 20px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
