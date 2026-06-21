<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">扩展配置</h2>
      <el-button type="primary" @click="handleBatchSave" :loading="saving">批量保存</el-button>
    </div>

    <el-alert
      v-if="hasChanges"
      title="有未保存的修改，请点击"批量保存"提交"
      type="warning"
      show-icon
      :closable="false"
      style="margin-bottom: 16px"
    />

    <div class="card-wrapper" v-loading="loading">
      <el-tabs v-model="activeGroup" @tab-click="handleTabClick">
        <el-tab-pane
          v-for="group in groups"
          :key="group"
          :label="groupLabel(group)"
          :name="group"
        />
      </el-tabs>

      <el-form label-width="240px" style="max-width: 700px; margin-top: 16px">
        <el-form-item
          v-for="cfg in filteredConfigs"
          :key="cfg.config_key"
          :label="cfg.description || cfg.config_key"
        >
          <template v-if="cfg.value_type === 'bool'">
            <el-switch
              v-model="configValues[cfg.config_key]"
              :disabled="cfg.is_readonly"
              @change="markChanged(cfg.config_key)"
            />
          </template>
          <template v-else-if="cfg.value_type === 'int'">
            <el-input-number
              v-model="configValues[cfg.config_key]"
              :disabled="cfg.is_readonly"
              @change="markChanged(cfg.config_key)"
            />
          </template>
          <template v-else-if="cfg.value_type === 'json'">
            <el-input
              v-model="configValues[cfg.config_key]"
              type="textarea"
              :rows="3"
              :disabled="cfg.is_readonly"
              @change="markChanged(cfg.config_key)"
            />
          </template>
          <template v-else>
            <el-input
              v-model="configValues[cfg.config_key]"
              :disabled="cfg.is_readonly"
              @change="markChanged(cfg.config_key)"
            />
          </template>
          <div v-if="cfg.is_readonly" style="color:#909399;font-size:12px;margin-top:4px">
            <el-icon><Lock /></el-icon> 只读配置
          </div>
          <div v-else-if="cfg.config_key" style="color:#c0c4cc;font-size:12px;margin-top:2px">
            {{ cfg.config_key }}
          </div>
        </el-form-item>

        <el-empty v-if="filteredConfigs.length === 0" description="该分组暂无配置" />
      </el-form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/utils/request'

const loading = ref(false)
const saving = ref(false)
const configs = ref([])
const groups = ref(['tracking', 'carrier', 'notification', 'global'])
const activeGroup = ref('tracking')
const configValues = reactive({})
const originalValues = reactive({})
const changedKeys = ref(new Set())

const hasChanges = computed(() => changedKeys.value.size > 0)

const groupLabel = (g) => {
  const map = { tracking: '轨迹回传', carrier: '承运商', notification: '通知', global: '全局' }
  return map[g] || g
}

const filteredConfigs = computed(() => {
  return configs.value.filter(c => c.config_group === activeGroup.value)
})

const loadConfigs = async () => {
  loading.value = true
  try {
    const res = await request.get('/extension/configs')
    configs.value = res || []
    configs.value.forEach(c => {
      if (c.value_type === 'json' && typeof c.config_value === 'string') {
        try {
          configValues[c.config_key] = JSON.stringify(JSON.parse(c.config_value), null, 2)
        } catch (e) {
          configValues[c.config_key] = c.config_value
        }
      } else {
        configValues[c.config_key] = c.config_value
      }
      originalValues[c.config_key] = configValues[c.config_key]
    })
    changedKeys.value = new Set()
  } catch (e) { /* ignore */ }
  loading.value = false
}

const markChanged = (key) => {
  if (configValues[key] !== originalValues[key]) {
    changedKeys.value = new Set([...changedKeys.value, key])
  } else {
    const next = new Set(changedKeys.value)
    next.delete(key)
    changedKeys.value = next
  }
}

const handleBatchSave = async () => {
  if (changedKeys.value.size === 0) {
    ElMessage.warning('没有需要保存的修改')
    return
  }

  saving.value = true
  try {
    const updates = {}
    for (const key of changedKeys.value) {
      updates[key] = configValues[key]
    }
    const res = await request.post('/extension/batch', { configs: updates })
    ElMessage.success('批量保存成功')

    configs.value = res || []
    configs.value.forEach(c => {
      if (c.value_type === 'json' && typeof c.config_value === 'string') {
        try {
          configValues[c.config_key] = JSON.stringify(JSON.parse(c.config_value), null, 2)
        } catch (e) {
          configValues[c.config_key] = c.config_value
        }
      } else {
        configValues[c.config_key] = c.config_value
      }
      originalValues[c.config_key] = configValues[c.config_key]
    })
    changedKeys.value = new Set()
  } catch (e) {
    ElMessage.error('批量保存失败，请检查配置值是否正确')
  }
  saving.value = false
}

const handleTabClick = () => {}

onMounted(() => {
  loadConfigs()
})
</script>
