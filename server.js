const express = require('express');
const cors = require('cors');
const multer = require('multer');
const fs = require('fs');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

// CORS untuk frontend
app.use(cors());
app.use(express.json());
app.use(express.static('public')); // serve frontend

// Upload configuration
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        const dir = 'uploads';
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        cb(null, dir);
    },
    filename: (req, file, cb) => {
        const unique = Date.now() + '-' + Math.round(Math.random() * 1E9);
        const ext = path.extname(file.originalname);
        cb(null, unique + ext);
    }
});
const upload = multer({ 
    storage: storage,
    limits: { fileSize: 4 * 1024 * 1024 * 1024 } // 4GB max
});

// Database file
const DB_PATH = 'fileDB.json';
function loadDB() {
    if (!fs.existsSync(DB_PATH)) return [];
    try {
        return JSON.parse(fs.readFileSync(DB_PATH, 'utf8'));
    } catch (e) {
        return [];
    }
}
function saveDB(data) {
    fs.writeFileSync(DB_PATH, JSON.stringify(data, null, 2));
}

// API Endpoints
app.get('/api/files', (req, res) => {
    const db = loadDB();
    const now = Date.now();
    
    // Filter expired files
    const activeFiles = db.filter(f => !f.expiresAt || f.expiresAt > now);
    
    // Update list if expired files exist
    if (activeFiles.length !== db.length) {
        saveDB(activeFiles);
    }
    
    res.json(activeFiles);
});

app.post('/api/upload', upload.single('file'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({ error: 'No file uploaded' });
    }

    const expiryMinutes = parseInt(req.body.expiry) || 0;
    const expiresAt = expiryMinutes > 0 
        ? Date.now() + (expiryMinutes * 60 * 1000) 
        : null;

    const fileRecord = {
        id: req.file.filename, // using filename as ID
        originalName: req.file.originalname,
        storedName: req.file.filename,
        path: req.file.path,
        size: req.file.size,
        uploadedAt: Date.now(),
        expiresAt: expiresAt,
        downloadCount: 0
    };

    const db = loadDB();
    db.unshift(fileRecord); // add to top
    saveDB(db);

    res.json({
        success: true,
        file: {
            id: fileRecord.id,
            name: fileRecord.originalName,
            size: fileRecord.size,
            url: `/download/${fileRecord.id}`,
            directUrl: `/download/${fileRecord.id}/${encodeURIComponent(fileRecord.originalName)}`
        }
    });
});

app.get('/download/:id', (req, res) => {
    const db = loadDB();
    const file = db.find(f => f.id === req.params.id);
    
    if (!file) {
        return res.status(404).json({ error: 'File not found' });
    }
    
    // Check expiry
    if (file.expiresAt && file.expiresAt < Date.now()) {
        return res.status(410).json({ error: 'File has expired' });
    }
    
    if (!fs.existsSync(file.path)) {
        return res.status(404).json({ error: 'File missing on server' });
    }

    // Increment download count
    file.downloadCount = (file.downloadCount || 0) + 1;
    saveDB(db);

    res.download(file.path, file.originalName);
});

app.delete('/api/delete/:id', (req, res) => {
    const db = loadDB();
    const index = db.findIndex(f => f.id === req.params.id);
    
    if (index === -1) {
        return res.status(404).json({ error: 'File not found' });
    }
    
    try {
        fs.unlinkSync(db[index].path);
        db.splice(index, 1);
        saveDB(db);
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ error: 'Delete failed' });
    }
});

// Clean expired files periodically (every minute)
setInterval(() => {
    const db = loadDB();
    const now = Date.now();
    const expired = db.filter(f => f.expiresAt && f.expiresAt < now);
    
    if (expired.length > 0) {
        expired.forEach(f => {
            try {
                if (fs.existsSync(f.path)) fs.unlinkSync(f.path);
            } catch (e) {
                console.error('Failed to delete expired file:', f.path);
            }
        });
        
        const remaining = db.filter(f => !f.expiresAt || f.expiresAt > now);
        saveDB(remaining);
        console.log(`Cleaned ${expired.length} expired files`);
    }
}, 60000);

app.listen(PORT, () => {
    console.log(`🚀 Server running on http://localhost:${PORT}`);
    console.log(`📁 Uploads directory: ${path.resolve('uploads')}`);
    console.log(`🗃️  Database: ${path.resolve(DB_PATH)}`);
});
