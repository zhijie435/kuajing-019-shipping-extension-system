<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">轨迹状态映射</h2>
      <el-button type="primary" @click="handleCreate">
        <el-icon><Plus /></el-icon>新增映射
      </el-button>
    </div>

    <div class="card-wrapper">
      <div class="filter-bar">
        <el-form :inline="true" @submit.prevent="loadMappings">
          <el-form-item label="承运商">
            <el-select v-model="filterCarrier" placeholder="全部" clearable @change="loadMappings">
              <el-option v-for="c in carriers" :key="c.carrier_code" :label="c.carrier_name" :value="c.carrier_code" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadMappings">查询</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table :data="mappings" v-loading="loading" border stripe>
        <el-table-column prop="carrier_name" label="承运商" width="130" />
        <el-table-column prop="carrier_code" label="编码" width="110" />
        <el-table-column prop="carrier_event_code" label="原始事件编码" width="150" />
        <el-table-column prop="carrier_event_desc" label="原始事件描述" min-width="160" />
        <el-table-column prop="standard_status" label="标准状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.standard_status)" size="small">
              {{ getMetaLabel(meta.standard_statuses, row.standard_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_exception" label="异常" width="70" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_exception ? 'danger' : 'info'" size="small">
              {{ row.is_exception ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="exception_type" label="异常类型" width="120" />
        <el-table-column prop="status" label="状态" width="70" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑映射' : '新增映射'" width="500px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="120px">
        <el-form-item label="承运商" prop="carrier_code">
          <el-select v-model="form.carrier_code" :disabled="isEdit" placeholder="请选择">
            <el-option v-for="c in carriers" :key="c.carrier_code" :label="c.carrier_name" :value="c.carrier_code" />
          </el-select>
        </el-form-item>
        <el-form-item label="原始事件编码" prop="carrier_event_code">
          <el-input v-model="form.carrier_event_code" :disabled="isEdit" placeholder="承运商的事件编码" />
        </el-form-item>
        <el-form-item label="原始事件描述" prop="carrier_event_desc">
          <el-input v-model="form.carrier_event_desc" placeholder="事件描述" />
        </el-form-item>
        <el-form-item label="标准状态" prop="standard_status">
          <el-select v-model="form.standard_status" placeholder="请选择">
            <el-option v-for="s in meta.standard_statuses" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="是否异常" prop="is_exception">
          <el-switch v-model="form.is_exception" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item v-if="form.is_exception" label="异常类型" prop="exception_type">
          <el-input v-model="form.exception_type" placeholder="如 shipping_abnormal" />
        </el-form-item>
        <el-form-item label="优先级" prop="priority">
          <el-input-number v-model="form.priority" :min="0" :max="100" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/utils/request'

const loading = ref(false)
const saving = ref(false)
const mappings = ref([])
const carriers = ref([])
const filterCarrier = ref('')
const dialogVisible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const formRef = ref(null)

const meta = reactive({ standard_statuses: [] })

const form = reactive({
  carrier_code: '',
  carrier_event_code: '',
  carrier_event_desc: '',
  standard_status: '',
  is_exception: 0,
  exception_type: '',
  priority: 10,
  status: 1,
})

const formRules = {
  carrier_code: [{ required: true, message: '请选择承运商', trigger: 'change' }],
  carrier_event_code: [{ required: true, message: '请输入原始事件编码', trigger: 'blur' }],
  standard_status: [{ required: true, message: '请选择标准状态', trigger: 'change' }],
}

const getMetaLabel = (arr, val) => {
  const item = arr.find(i => i.value === val)
  return item ? item.label : String(val)
}

const statusTagType = (status) => {
  const map = {
    PICKED_UP: 'info', IN_TRANSIT: '', OUT_FOR_DELIVERY: 'warning',
    DELIVERED: 'success', EXCEPTION: 'danger', UNKNOWN: 'info',
  }
  return map[status] || 'info'
}

const loadMeta = async () => {
  try {
    const res = await request.get('/meta/all')
    meta.standard_statuses = res.standard_statuses || []
  } catch (e) { /* ignore */ }
}

const loadCarriers = async () => {
  try {
    const res = await request.get('/carriers', { params: { page_size: 100 } })
    carriers.value = res.items || []
  } catch (e) { /* ignore */ }
}

const loadMappings = async () => {
  loading.value = true
  try {
    const res = await request.get('/extension/mappings', {
      params: filterCarrier.value ? { carrier_code: filterCarrier.value } : {},
    })
    mappings.value = res || []
  } catch (e) { /* ignore */ }
  loading.value = false
}

const handleCreate = () => {
  isEdit.value = false
  editId.value = null
  Object.assign(form, {
    carrier_code: '', carrier_event_code: '', carrier_event_desc: '',
    standard_status: '', is_exception: 0, exception_type: '', priority: 10, status: 1,
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  editId.value = row.id
  Object.assign(form, {
    carrier_code: row.carrier_code,
    carrier_event_code: row.carrier_event_code,
    carrier_event_desc: row.carrier_event_desc,
    standard_status: row.standard_status,
    is_exception: row.is_exception,
    exception_type: row.exception_type,
    priority: row.priority,
    status: row.status,
  })
  dialogVisible.value = true
}

const handleSave = async () => {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) {
    ElMessage.warning('请修正表单中的错误项后再提交')
    return
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await request.post(`/extension/mapping_item/${editId.value}`, form)
      ElMessage.success('映射更新成功')
    } else {
      await request.post('/extension/mappings', form)
      ElMessage.success('映射创建成功')
    }
    dialogVisible.value = false
    await loadMappings()
  } catch (e) {
    ElMessage.error(isEdit.value ? '映射更新失败' : '映射创建失败')
  }
  saving.value = false
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定删除该映射规则？', '确认删除', { type: 'warning' })
    await request.delete(`/extension/mapping_item/${row.id}`)
    ElMessage.success('删除成功')
    await loadMappings()
  } catch (e) { /* cancel */ }
}

onMounted(() => {
  loadMeta()
  loadCarriers()
  loadMappings()
})
</script>
