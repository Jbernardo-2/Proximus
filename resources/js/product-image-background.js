import * as ort from 'onnxruntime-web/webgpu';
import ortWasmUrl from 'onnxruntime-web/ort-wasm-simd-threaded.jsep.wasm?url';
import modelUrl from '../models/u2netp.onnx?url';

const MODEL_SIZE = 320;
const MAX_SOURCE_SIZE = 2048;
const OUTPUT_SIZE = 1200;
const OUTPUT_PADDING = 96;
const MASK_THRESHOLD = 16;
const CHANNEL_MEANS = [0.485, 0.456, 0.406];
const CHANNEL_DEVIATIONS = [0.229, 0.224, 0.225];

let sessionPromise;

export async function removeProductImageBackground(file) {
    const session = await getSession();
    const sourceCanvas = await loadSourceCanvas(file);
    const inputTensor = createInputTensor(sourceCanvas);
    const results = await session.run({ [session.inputNames[0]]: inputTensor });
    const outputTensor = results[session.outputNames[0]];

    try {
        const mask = createMaskCanvas(outputTensor);

        return await createUniformProductFile(sourceCanvas, mask, file.name);
    } finally {
        inputTensor.dispose?.();
        Object.values(results).forEach((tensor) => tensor.dispose?.());
    }
}

function getSession() {
    sessionPromise ??= createSession().catch((error) => {
        sessionPromise = undefined;

        throw error;
    });

    return sessionPromise;
}

async function createSession() {
    ort.env.wasm.numThreads = 1;
    ort.env.wasm.wasmPaths = {
        'ort-wasm-simd-threaded.jsep.wasm': ortWasmUrl,
    };

    const commonOptions = {
        graphOptimizationLevel: 'all',
    };

    if ('gpu' in navigator) {
        try {
            return await ort.InferenceSession.create(modelUrl, {
                ...commonOptions,
                executionProviders: ['webgpu', 'wasm'],
            });
        } catch (error) {
            console.warn('WebGPU no está disponible; Proximus usará WebAssembly.', error);
        }
    }

    return ort.InferenceSession.create(modelUrl, {
        ...commonOptions,
        executionProviders: ['wasm'],
    });
}

async function loadSourceCanvas(file) {
    const image = await decodeImage(file);
    const scale = Math.min(1, MAX_SOURCE_SIZE / Math.max(image.width, image.height));
    const width = Math.max(1, Math.round(image.width * scale));
    const height = Math.max(1, Math.round(image.height * scale));
    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;

    const context = getCanvasContext(canvas);

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    context.drawImage(image.element, 0, 0, width, height);
    image.close?.();

    return canvas;
}

async function decodeImage(file) {
    if ('createImageBitmap' in window) {
        let bitmap;

        try {
            bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            bitmap = await createImageBitmap(file);
        }

        return {
            element: bitmap,
            width: bitmap.width,
            height: bitmap.height,
            close: () => bitmap.close(),
        };
    }

    const objectUrl = URL.createObjectURL(file);

    const image = new Image();
    image.src = objectUrl;

    try {
        await image.decode();
    } catch (error) {
        URL.revokeObjectURL(objectUrl);

        throw error;
    }

    return {
        element: image,
        width: image.naturalWidth,
        height: image.naturalHeight,
        close: () => URL.revokeObjectURL(objectUrl),
    };
}

function createInputTensor(sourceCanvas) {
    const canvas = document.createElement('canvas');

    canvas.width = MODEL_SIZE;
    canvas.height = MODEL_SIZE;

    const context = getCanvasContext(canvas, true);
    context.drawImage(sourceCanvas, 0, 0, MODEL_SIZE, MODEL_SIZE);

    const pixels = context.getImageData(0, 0, MODEL_SIZE, MODEL_SIZE).data;
    const planeSize = MODEL_SIZE * MODEL_SIZE;
    const values = new Float32Array(planeSize * 3);

    for (let pixelIndex = 0; pixelIndex < planeSize; pixelIndex += 1) {
        const rgbaIndex = pixelIndex * 4;

        for (let channel = 0; channel < 3; channel += 1) {
            const normalized = pixels[rgbaIndex + channel] / 255;
            values[(channel * planeSize) + pixelIndex] = (
                normalized - CHANNEL_MEANS[channel]
            ) / CHANNEL_DEVIATIONS[channel];
        }
    }

    return new ort.Tensor('float32', values, [1, 3, MODEL_SIZE, MODEL_SIZE]);
}

function createMaskCanvas(outputTensor) {
    const dimensions = outputTensor.dims;
    const width = dimensions[dimensions.length - 1];
    const height = dimensions[dimensions.length - 2];
    const values = outputTensor.data;
    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;

    const context = getCanvasContext(canvas);
    const imageData = context.createImageData(width, height);
    let minimum = Number.POSITIVE_INFINITY;
    let maximum = Number.NEGATIVE_INFINITY;

    for (const value of values) {
        minimum = Math.min(minimum, value);
        maximum = Math.max(maximum, value);
    }

    const range = maximum - minimum || 1;

    for (let index = 0; index < width * height; index += 1) {
        const rgbaIndex = index * 4;
        const alpha = Math.round(((values[index] - minimum) / range) * 255);

        imageData.data[rgbaIndex] = 255;
        imageData.data[rgbaIndex + 1] = 255;
        imageData.data[rgbaIndex + 2] = 255;
        imageData.data[rgbaIndex + 3] = alpha;
    }

    context.putImageData(imageData, 0, 0);

    return canvas;
}

async function createUniformProductFile(sourceCanvas, modelMask, originalName) {
    const scaledMask = document.createElement('canvas');

    scaledMask.width = sourceCanvas.width;
    scaledMask.height = sourceCanvas.height;

    const maskContext = getCanvasContext(scaledMask, true);
    maskContext.imageSmoothingEnabled = true;
    maskContext.imageSmoothingQuality = 'high';
    maskContext.drawImage(modelMask, 0, 0, scaledMask.width, scaledMask.height);

    const bounds = findForegroundBounds(maskContext, scaledMask.width, scaledMask.height);
    const cutout = document.createElement('canvas');

    cutout.width = sourceCanvas.width;
    cutout.height = sourceCanvas.height;

    const cutoutContext = getCanvasContext(cutout);
    cutoutContext.drawImage(sourceCanvas, 0, 0);
    cutoutContext.globalCompositeOperation = 'destination-in';
    cutoutContext.drawImage(scaledMask, 0, 0);

    const output = document.createElement('canvas');

    output.width = OUTPUT_SIZE;
    output.height = OUTPUT_SIZE;

    const outputContext = getCanvasContext(output);
    const availableSize = OUTPUT_SIZE - (OUTPUT_PADDING * 2);
    const boundsWidth = bounds.right - bounds.left + 1;
    const boundsHeight = bounds.bottom - bounds.top + 1;
    const scale = Math.min(availableSize / boundsWidth, availableSize / boundsHeight);
    const targetWidth = Math.max(1, Math.round(boundsWidth * scale));
    const targetHeight = Math.max(1, Math.round(boundsHeight * scale));
    const targetX = Math.floor((OUTPUT_SIZE - targetWidth) / 2);
    const targetY = Math.floor((OUTPUT_SIZE - targetHeight) / 2);

    outputContext.fillStyle = '#ffffff';
    outputContext.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    outputContext.imageSmoothingEnabled = true;
    outputContext.imageSmoothingQuality = 'high';
    outputContext.drawImage(
        cutout,
        bounds.left,
        bounds.top,
        boundsWidth,
        boundsHeight,
        targetX,
        targetY,
        targetWidth,
        targetHeight,
    );

    const blob = await canvasToBlob(output);
    const baseName = originalName.replace(/\.[^.]+$/, '') || 'producto';

    return new File([blob], baseName + '-proximus.jpg', {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
}

function findForegroundBounds(context, width, height) {
    const pixels = context.getImageData(0, 0, width, height).data;
    let left = width;
    let top = height;
    let right = -1;
    let bottom = -1;

    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            if (pixels[((y * width) + x) * 4 + 3] <= MASK_THRESHOLD) {
                continue;
            }

            left = Math.min(left, x);
            top = Math.min(top, y);
            right = Math.max(right, x);
            bottom = Math.max(bottom, y);
        }
    }

    if (right < left || bottom < top) {
        throw new Error('No fue posible identificar el producto en la fotografía.');
    }

    return { left, top, right, bottom };
}

function canvasToBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);

                return;
            }

            reject(new Error('No fue posible generar la fotografía final.'));
        }, 'image/jpeg', 0.9);
    });
}

function getCanvasContext(canvas, frequentlyRead = false) {
    const context = canvas.getContext('2d', { willReadFrequently: frequentlyRead });

    if (! context) {
        throw new Error('El navegador no permite procesar esta fotografía.');
    }

    return context;
}
